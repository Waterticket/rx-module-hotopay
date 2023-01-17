<?php
/**
 * Hoto Pay
 * Hoto Pay Controller
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 * 
 * @package HotoPay
 * @author Waterticket
 * @copyright Copyright (c) Waterticket
 */

class HotopayController extends Hotopay
{
	/**
	 * PG사로 데이터를 넘기기 전에 Form에서 넘어온 데이터를 처리합니다
	 * 
	 * @return void
	 */
	public function procHotopayOrderProcess()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');
		if($logged_info == null) return $this->createObject(-1, "로그인이 필요합니다");

		if($config->hotopay_purchase_enabled !== 'Y')
		{
			return $this->createObject(-1, "현재 결제 기능 점검중입니다. 잠시 뒤에 다시 시도 해주세요.");
		}

		$oHotopayModel = getModel('hotopay');
		$order_id = getNextSequence();

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $logged_info->member_srl;

		$product_srl_list = [];
		$product_list = [];
		foreach ($vars->purchase_info as $product)
		{
			if(!isset($product['option_srl'])) return $this->createObject(-1, "유효한 옵션을 선택해주세요");

			$product_srl_list[] = $product['product_srl'];
			$obj = new stdClass();
			$obj->product_srl = $product['product_srl'];
			$obj->option_srl = $product['option_srl'];
			$obj->cart_item_srl = $product['cart_item_srl'];
			$obj->quantity = $product['quantity'] ?: 1;
			$product_list[] = $obj;
		}

		$product_info = $oHotopayModel->getProducts($product_srl_list);

		foreach ($product_list as &$product)
		{
			foreach ($product_info as $info)
			{
				if($product->product_srl == $info->product_srl)
				{
					$product->info = $info;
					break;
				}
			}
		}

		$title = "";
		$tc = -1;
		$total_price = 0;
		$original_price = 0;

		foreach($product_list as $product)
		{
			$option_srl = $product->option_srl;
			$option = $product->info->product_option[$option_srl];

			if($option->infinity_stock != 'Y' && $option->stock < 1) return $this->createObject(-1, "재고가 부족한 항목이 있습니다.");
		}

		foreach($product_list as $product_meta)
		{
			$option_srl = $product_meta->option_srl;
			$cart_item_srl = $product_meta->cart_item_srl ?? null;
			$quantity = $product_meta->quantity;
			$product = $product_meta->info;

			$option = $product->product_option[$option_srl];

			if($tc < 0)
			{
				$title = $product->product_name;
				$tc++;
			}


			$obj = new StdClass();
			$obj->item_srl = getNextSequence();
			$obj->purchase_srl = $order_id;
			$obj->product_srl = $product->product_srl;
			$obj->option_srl = $option_srl;
			$obj->option_name = $option->title;
			$obj->purchase_price = ($option->price + round($option->price * ($product->tax_rate / 100))) * $quantity;
			$obj->original_price = $option->price * $quantity;
			$obj->quantity = $quantity;
			$obj->extra_vars = serialize($option->extra_vars ?: new stdClass());
			$obj->regdate = time();
			executeQuery('hotopay.insertPurchaseItem', $obj);

			if ($cart_item_srl)
			{
				$oHotopayModel->deleteCartItem($cart_item_srl, $logged_info->member_srl);
			}

			$total_price += $obj->purchase_price;
			$original_price += $obj->original_price;

			if($option->infinity_stock != 'Y')
			{
				$oHotopayModel->minusOptionStock($option_srl, 1);
			}
		}

		if($tc > 0)
		{
			$title .= " 외 ".$tc."개";
		}

		$extra_vars = new stdClass(); // $vars->extra_vars ?? new stdClass();
		$extra_vars->use_point = 0;

		if ($config->point_discount == 'Y')
		{
			$oPointModel = getModel('point');
			$user_point = $oPointModel->getPoint($logged_info->member_srl, true);
			$input_point = intval($vars->use_point, 10) ?? 0;

			if ($input_point < 0) $input_point = 0;
			
			if ($input_point > $user_point)
			{
				return $this->createObject(-1, "포인트가 부족합니다.");
			}

			if ($input_point > $total_price)
			{
				return $this->createObject(-1, "포인트가 결제 금액보다 많습니다.");
			}

            Context::set('__point_message_id__', 'module.hotopay.buy_point_discount');
            Context::set('__point_message__', sprintf("[%s] 상품 구매시에 포인트를 사용하였습니다.", $title));
			$oPointController = getController('point');
			$oPointController->setPoint($logged_info->member_srl, $input_point, 'minus');

			$total_price -= $input_point;

			if ($total_price <= 0) $vars->pay_method = 'point';
			$extra_vars->use_point = $input_point;
		}

		$args->title = $title;
		$args->products = json_encode(array("t"=>$title)); // 구시대의 유물
		$args->pay_method = $vars->pay_method;
		$args->product_purchase_price = $total_price;
		$args->product_original_price = $original_price;
		$args->pay_status = "PENDING";
		$args->regdate = time();
		$args->pay_data = '';
		$args->extra_vars = serialize($extra_vars);

		$pg = $vars->pay_method;
		if(substr($pg, 0, 6) === 'paypl_')
		{
			$pg = 'payple';
		}

		switch($pg)
		{
			case 'paypal':
				$usd_total = $oHotopayModel->changeCurrency('KRW', 'USD', $total_price);

				$paypalController = new Paypal();
		
				$obj1 = (object) array(
					"name" => $title,
					"description" => $config->shop_name."에서 판매하는 상품입니다.",
					"value" => $usd_total,
					"count" => 1,
				);
				$order = new stdClass();
				$order->purchase = new stdClass();
				$order->purchase->total = $usd_total;
				$order->purchase->currency_code = 'USD';
				$order->items = array(
					$obj1,
				);
		
				$order_obj = $paypalController->createOrder($order, $order_id);
				$args->pay_data = json_encode($order_obj);
				break;

			case 'kakaopay':
				$kakaoPayController = new KakaoPay();
				$order_obj = $kakaoPayController->createOrder($args, $order_id, $logged_info->user_id);
				$args->pay_data = json_encode($order_obj);
				break;

			case 'payple':
				$paypleController = new Payple();
				$partner_auth = $paypleController->getPartnerAuth();
				if ($partner_auth->error != 0)
				{
					return $this->createObject(-1, $partner_auth->message);
				}

				$args->pay_data = json_encode($partner_auth->data);
				break;

			case "n_account":
				$order_obj = new stdClass();
				$order_obj->depositor_name = $vars->depositor_name ?: mb_substr(($logged_info->user_name ?: ('구매자'.rand(100, 999))), 0, 6);
				$args->pay_data = json_encode($order_obj);
				break;
		}

		executeQuery("hotopay.insertPurchase", $args);

		header('HTTP/1.1 307 Temporary move');
		header('Location: ' . getNotEncodedUrl('','mid','hotopay','act','procHotopayPayProcess','order_id',$order_id));
		return;
	}

	public function procHotopayPayProcess()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');

		Context::set('logged_info', $logged_info);
		Context::set('hotopay_config', $config);
		Context::set('vars', $vars);

		if(!empty($_SESSION['hotopay_HT'.$vars->order_id]))
		{
			return $this->createObject(-1, "이미 진행된 결제입니다.");
		}

		$args = new stdClass();
		$args->purchase_srl = $vars->order_id;
		$purchase = executeQuery('hotopay.getPurchase', $args);
		$purchase_data = $purchase->data;

		if($purchase_data->member_srl != $logged_info->member_srl)
		{
			return $this->createObject(-1, "결제 실패. (CODE: -1000)");
		}

		if(!empty($purchase_data->pay_data)) // pay_data가 있다면
			$purchase_data->pay_data = json_decode($purchase_data->pay_data);

		$products = json_decode($purchase_data->products);
		$purchase_data->title = $products->t;
		switch($purchase_data->pay_method)
		{
			case 'card':
				$purchase_data->pay_method_korean = '카드';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'ts_account':
				$purchase_data->pay_method_korean = '계좌이체';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'v_account':
				$purchase_data->pay_method_korean = '가상계좌';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'voucher':
				$purchase_data->pay_method_korean = '문화상품권';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'cellphone':
				$purchase_data->pay_method_korean = '휴대폰';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'kakaopay':
				$purchase_data->pay_method_korean = '카카오페이';
				$purchase_data->pay_pg = 'kakaopay';
				break;

			case 'n_account':
				$purchase_data->pay_method_korean = '무통장 입금';
				$purchase_data->pay_pg = 'n_account';
				break;

			case 'paypal':
				$purchase_data->pay_method_korean = 'PayPal';
				$purchase_data->pay_pg = 'paypal';
				break;

			case 'toss':
				$purchase_data->pay_method_korean = '토스결제';
				$purchase_data->pay_pg = 'toss';
				break;

			case 'point':
				$purchase_data->pay_method_korean = '포인트 결제';
				$purchase_data->pay_pg = 'point';
				break;
		}

		if(substr($purchase_data->pay_method, 0, 5) === 'inic_')
		{
			$purchase_data->pay_method_korean = '이니시스';
			$purchase_data->pay_pg = 'inicis';
		}

		if(substr($purchase_data->pay_method, 0, 6) === 'paypl_')
		{
			$purchase_data->pay_method_korean = '페이플';
			$purchase_data->pay_pg = 'payple';
		}

		Context::set('purchase', $purchase_data);

		$this->setTemplateFile('pay_process');
	}

	/**
	 * PG사에서 넘어온 결제 데이터를 처리합니다
	 * Success라면 결제 승인 명령을 PG사로 보냅니다
	 * 
	 * @return void
	 */
	public function procHotopayPayStatus()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');
		$oHotopayModel = getModel('hotopay');

		$purchase_srl = (int)substr($vars->order_id, 2);
		if (!$purchase_srl) return $this->createObject(-1, "잘못된 주문번호입니다.");

		if(strcmp($vars->pay_status, "success") === 0) // 결제 성공
		{
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$purchase = executeQuery('hotopay.getPurchase', $args);
			if(!$purchase->toBool())
			{
				return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
			}

			if($purchase->data->pay_status === "DONE")
			{
				return $this->createObject(-1, "이미 결제가 완료되었습니다.");
			}

			if(strcmp($vars->pay_pg, "toss") === 0) // Toss 처리
			{
				if(strcmp($vars->order_id, $vars->orderId) !== 0)
				{
					return $this->createObject(-1, "결제 실패. (code: 1003)");
				}
	
				if($purchase->data->product_purchase_price != $vars->amount)
				{
					return $this->createObject(-1, "결제 실패.");
				}

				$tossController = new Toss();
				$output = $tossController->acceptOrder($purchase_srl, $vars->paymentKey);
				$response_json = $output->data;
				$http_code = $output->http_code;

				$args->pay_data = json_encode($response_json);
				$args->pay_status = $response_json->status;
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);

				if($http_code !== 200)
				{
					$_SESSION['hotopay_'.$vars->orderId] = array(
						"p_status" => "failed",
						"orderId" => $vars->orderId,
						"code" => $response_json->code,
						"message" => $response_json->message
					);

					$args = new stdClass();
					$args->purchase_srl = $purchase_srl;
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = $response_json;
					$trigger_obj->pay_pg = "toss";
					$trigger_obj->amount = $vars->amount;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
					return;
					//echo $http_code; //{"code":"ALREADY_PROCESSED_PAYMENT","message":"이미 처리된 결제 입니다."}
				}

				if(strcmp($response_json->status,"DONE") === 0) // 결제 완료에 경우
				{
					$this->_ActivePurchase($purchase_srl);
					$receipt_args = new stdClass();
					$receipt_args->purchase_srl = $purchase_srl;
					$receipt_args->receipt_url = $response_json->receipt->url ?? "";
					executeQuery('hotopay.updatePurchaseReceiptUrl', $receipt_args);
				}

				if($purchase->data->pay_method == 'v_account')
				{
					if($response_json->status == 'WAITING_FOR_DEPOSIT')
					{
						$this->_MessageMailer("WAITING_FOR_DEPOSIT", $purchase->data);
					}
				}

				$response_json->p_status = "success";
				$response_json->product_title = $purchase->data->title;
				$_SESSION['hotopay_'.$vars->orderId] = $response_json;

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = $response_json->status;
				$trigger_obj->pay_data = $response_json;
				$trigger_obj->pay_pg = "toss";
				$trigger_obj->amount = $vars->amount;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
				return;
			}
			else if(strcmp($vars->pay_pg, "paypal") === 0) // PayPal 처리
			{
				if(empty($vars->token) || empty($vars->PayerID))
				{
					return $this->createObject(-1, "결제 실패. (code: 1004)");
				}

				$pay_data = json_decode($purchase->data->pay_data);
				$paypalController = new Paypal();
				$order_detail = $paypalController->getOrderDetails($pay_data->id);

				if(strcmp($order_detail->status,"APPROVED") === 0) // 결제 완료에 경우
				{
					$capture_output = $paypalController->captureOrder($pay_data->id);
					
					$args = new stdClass();
					$args->purchase_srl = $purchase_srl;
					$args->pay_data = json_encode($capture_output);
					executeQuery('hotopay.updatePurchaseData', $args);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "DONE";
					$trigger_obj->pay_data = $order_detail;
					$trigger_obj->pay_pg = "paypal";
					$trigger_obj->amount = $vars->amount;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->_ActivePurchase($purchase_srl);
				}
				else
				{
					$_SESSION['hotopay_'.$vars->orderId] = array(
						"p_status" => "failed",
						"orderId" => $vars->orderId,
						"code" => $order_detail->status,
						"message" => "PayPal 결제에 실패하였습니다."
					);

					$args = new stdClass();
					$args->purchase_srl = $purchase_srl;
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = $order_detail;
					$trigger_obj->pay_pg = "paypal";
					$trigger_obj->amount = $vars->amount;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
					return;
				}

				$order_detail->orderId = $vars->order_id;
				$order_detail->p_status = "success";
				$order_detail->method = "paypal";
				$order_detail->product_title = $purchase->data->title;
				$_SESSION['hotopay_'.$vars->orderId] = $order_detail;
				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
				return;
			}
			else if(strcmp($vars->pay_pg, "kakaopay") === 0) // 카카오페이 처리
			{
				if(empty($vars->pg_token))
				{
					return $this->createObject(-1, "결제 실패. (code: 1005)");
				}

				$pg_token = $vars->pg_token;

				$kakaoPayController = new KakaoPay();
				$output = $kakaoPayController->acceptOrder($purchase_srl, $pg_token, $logged_info->user_id);
				$response_json = $output->data;
				$http_code = $output->http_code;

				$args->pay_data = json_encode($response_json);
				$args->pay_status = isset($response_json->approved_at) ? 'DONE' : 'FAILED';
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);

				if($http_code !== 200)
				{
					$_SESSION['hotopay_'.$vars->order_id] = array(
						"p_status" => "failed",
						"orderId" => $vars->order_id,
						"code" => $response_json->code,
						"message" => $response_json->msg
					);

					$args = new stdClass();
					$args->purchase_srl = $purchase_srl;
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = $response_json;
					$trigger_obj->pay_pg = "kakao";
					$trigger_obj->amount = $vars->amount;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
					return;
				}

				if(isset($response_json->approved_at)) // 결제 완료에 경우
				{
					$this->_ActivePurchase($purchase_srl);
				}

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = "DONE";
				$trigger_obj->pay_data = $response_json;
				$trigger_obj->pay_pg = "kakao";
				$trigger_obj->amount = $vars->amount;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$response_json->method = 'kakaopay';
				$response_json->p_status = "success";
				$response_json->product_title = $purchase->data->title;
				$response_json->orderId = $vars->order_id;
				$_SESSION['hotopay_'.$vars->order_id] = $response_json;
				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else if(strcmp($vars->pay_pg, "inicis") === 0) // 이니시스 처리
			{
				if ($vars->merchant_uid != $vars->order_id)
				{
					return $this->createObject(-1, "결제 실패. (code: 1006)");
				}

				if (empty($vars->imp_uid))
				{
					return $this->createObject(-1, "결제 실패. (code: 1007)");
				}

				$imp_uid = $vars->imp_uid;
				$args->iamport_uid = $imp_uid;
				executeQuery('hotopay.updatePurchaseIamportUid', $args);

				$iamport = new Iamport();
				$imp_purchase = $iamport->getPaymentByImpUid($imp_uid);

				$args->pay_data = json_encode($imp_purchase);
				switch($imp_purchase->status)
				{
					case "paid":
						if ($imp_purchase->amount == $purchase->data->product_purchase_price)
						{
							$args->pay_status = "DONE";
						}
						else
						{
							$args->pay_status = "FAILED";
						}
						break;
					case "ready":
						$args->pay_status = "WAITING_FOR_DEPOSIT";
						break;
					case "cancelled":
						$args->pay_status = "CANCELED";
						break;
					default:
						$args->pay_status = "FAILED";
						break;
				}
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);

				if($args->pay_status == "FAILED" || $args->pay_status == "CANCELED")
				{
					$_SESSION['hotopay_'.$vars->order_id] = array(
						"p_status" => "failed",
						"orderId" => $vars->order_id,
						"code" => "IAMPORT_FAILED",
						"message" => "결제를 실패하였습니다. (code: 1008)"
					);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = $imp_purchase;
					$trigger_obj->pay_pg = "inicis";
					$trigger_obj->amount = $imp_purchase->amount;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
					return;
				}

				if($args->pay_status == "DONE") // 결제 완료에 경우
				{
					$this->_ActivePurchase($purchase_srl);
				}
				else if($args->pay_status = "WAITING_FOR_DEPOSIT")
				{
					$purchase = executeQuery('hotopay.getPurchase', $args);
					$this->_MessageMailer("WAITING_FOR_DEPOSIT", $purchase->data);
				}

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = $args->pay_status;
				$trigger_obj->pay_data = $imp_purchase;
				$trigger_obj->pay_pg = "inicis";
				$trigger_obj->amount = $imp_purchase->amount;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$response_json = new stdClass();
				$response_json->method = 'inicis';
				$response_json->p_status = "success";
				$response_json->product_title = $purchase->data->title;
				$response_json->orderId = $vars->order_id;
				$response_json->pay_status = $args->pay_status;
				$response_json->pay_data = $imp_purchase;
				$_SESSION['hotopay_'.$vars->order_id] = $response_json;
				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else if(strcmp($vars->pay_pg, "payple") === 0) // 페이플 처리
			{
				if ($vars->PCD_PAY_OID != $vars->order_id)
				{
					return $this->createObject(-1, "결제 실패. (code: 1010)");
				}

				$args->pay_data = json_encode($vars);
				if ($vars->PCD_PAY_RST == 'success' && $vars->PCD_PAY_CODE == '0000')
				{
					$args->pay_status = "DONE";
				}
				else
				{
					$args->pay_status = "FAILED";
				}
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);

				if($args->pay_status == "DONE") // 결제 완료에 경우
				{
					$payple = new Payple();
					$result = $payple->confirmPaywork($vars, $purchase->data);
					if (!$result->toBool())
					{
						$args->pay_status == "FAILED";
					}
					else
					{
						$this->_ActivePurchase($purchase_srl);
						$receipt_args = new stdClass();
						$receipt_args->purchase_srl = $purchase_srl;
						$receipt_args->receipt_url = $result->PCD_PAY_CARDRECEIPT ?? "";
						executeQuery('hotopay.updatePurchaseReceiptUrl', $receipt_args);

						if ($config->payple_purchase_type == 'password')
						{
							if (isset($_SESSION['hotopay_purchase_key_idx']))
							{
								$before_idx = $_SESSION['hotopay_purchase_key_idx'];
								$key = $oHotopayModel->getBillingKey($before_idx);
								if (!empty($key->key) && $key->key != $result->PCD_PAYER_ID)
								{
									// update
									$billing_key_obj = new stdClass();
									$billing_key_obj->key_idx = $before_idx;
									$billing_key_obj->key = $result->PCD_PAYER_ID;
									switch ($reesult->PCD_PAY_TYPE)
									{
										case 'card':
										default:
											$billing_key_obj->alias = $result->PCD_PAY_CARDNAME;
											$billing_key_obj->number = $result->PCD_PAY_CARDNUM ?? '0000-****-****-0000';
											break;

										case 'transfer':
											$billing_key_obj->alias = $result->PCD_PAY_BANKNAME;
											$billing_key_obj->number = $result->PCD_PAY_BANKNUM ?? '0000*******0000';
											break;
									}

									$oHotopayModel->updateBillingKey($billing_key_obj);
								}
							}
							else
							{
								$billing_key_obj = new stdClass();
								$billing_key_obj->key_idx = getNextSequence();
								$billing_key_obj->member_srl = $purchase->data->member_srl;
								$billing_key_obj->pg = 'payple';
								$billing_key_obj->type = 'password';
								$billing_key_obj->key = $result->PCD_PAYER_ID;
								$billing_key_obj->regdate = time();

								switch ($reesult->PCD_PAY_TYPE)
								{
									case 'card':
									default:
										$billing_key_obj->alias = $result->PCD_PAY_CARDNAME ?? 'CARD';
										$billing_key_obj->number = $result->PCD_PAY_CARDNUM ?? '0000-****-****-0000';
										break;

									case 'transfer':
										$billing_key_obj->alias = $result->PCD_PAY_BANKNAME ?? 'BANK';
										$billing_key_obj->number = $result->PCD_PAY_BANKNUM ?? '0000*******0000';
										break;
								}

								$oHotopayModel->insertBillingKey($billing_key_obj);
							}
						}
					}
				}

				if($args->pay_status == "FAILED")
				{
					$_SESSION['hotopay_'.$vars->order_id] = array(
						"p_status" => "failed",
						"orderId" => $vars->order_id,
						"code" => "PAYPLE_FAILED",
						"message" => "결제를 실패하였습니다. ".$vars->PCD_PAY_MSG." (code: 1011)"
					);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = $vars;
					$trigger_obj->pay_pg = "payple";
					$trigger_obj->amount = $vars->PCD_PAY_AMOUNT;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
					return;
				}

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = $args->pay_status;
				$trigger_obj->pay_data = $vars;
				$trigger_obj->pay_pg = "payple";
				$trigger_obj->amount = $vars->PCD_PAY_AMOUNT;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$response_json = new stdClass();
				$response_json->method = 'payple';
				$response_json->p_status = "success";
				$response_json->product_title = $vars->PCD_PAY_GOODS;
				$response_json->orderId = $vars->order_id;
				$response_json->pay_status = $args->pay_status;
				$response_json->pay_data = $vars;
				$_SESSION['hotopay_'.$vars->order_id] = $response_json;
				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else if(strcmp($vars->pay_pg, "n_account") === 0) // 무통장 처리
			{
				$args->pay_status = 'WAITING_FOR_DEPOSIT';
				executeQuery('hotopay.updatePurchaseStatus', $args);

				$this->_MessageMailer("WAITING_FOR_DEPOSIT", $purchase->data);
				$pay_data = json_decode($purchase->data->pay_data);

				$order_detail = new stdClass();
				$order_detail->orderId = $vars->order_id;
				$order_detail->p_status = "success";
				$order_detail->method = "n_account";
				$order_detail->totalAmount = $purchase->data->product_purchase_price;
				$order_detail->depositor_name = $pay_data->depositor_name;
				$order_detail->product_title = $purchase->data->title;

				$_SESSION['hotopay_'.$vars->order_id] = $order_detail;

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = "WAITING_FOR_DEPOSIT";
				$trigger_obj->pay_data = $order_detail;
				$trigger_obj->pay_pg = "n_account";
				$trigger_obj->amount = $order_detail->totalAmount;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else if(strcmp($vars->pay_pg, "point") === 0) // 포인트 결제 (0원) 처리
			{
				if ($purchase->data->product_purchase_price !== 0)
				{
					$_SESSION['hotopay_'.$vars->order_id] = array(
						"p_status" => "failed",
						"orderId" => $vars->order_id,
						"code" => "POINT_PURCHASE_FAILED",
						"message" => "결제를 실패하였습니다. (code: 1009)"
					);

					$oHotopayModel->rollbackOptionStock($purchase_srl);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $purchase_srl;
					$trigger_obj->pay_status = "FAILED";
					$trigger_obj->pay_data = new stdClass();
					$trigger_obj->pay_pg = "point";
					$trigger_obj->amount = $purchase->data->product_purchase_price;
					ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

					$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
					return;
				}

				$args->pay_status = 'DONE';
				executeQuery('hotopay.updatePurchaseStatus', $args);

				$this->_MessageMailer("DONE", $purchase->data);

				$order_detail = new stdClass();
				$order_detail->orderId = $vars->order_id;
				$order_detail->p_status = "success";
				$order_detail->method = "point";
				$order_detail->product_title = $purchase->data->title;

				$_SESSION['hotopay_'.$vars->order_id] = $order_detail;

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = "DONE";
				$trigger_obj->pay_data = new stdClass();
				$trigger_obj->pay_pg = "point";
				$trigger_obj->amount = $purchase->data->product_purchase_price;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else
			{
				return $this->createObject(-1, "결제 실패. (code: 3002)");
			}
		}
		else // 결제 실패
		{
			$code = Context::get('code');
			$message = Context::get('message');
			$order_id = Context::get('order_id');

			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = "FAILED";

			$oHotopayModel->rollbackOptionStock($purchase_srl);

			$res_array = array(
				"p_status" => "failed",
				"orderId" => $order_id,
				"code" => $code,
				"message" => $message
			);

			if(strcmp($code, "PAY_PROCESS_CANCELED") === 0)
			{
				$res_array['status'] = "CANCELED";
				$args->pay_status = "CANCELED";
			}

			if(strcmp($vars->pay_status, "cancel") === 0)
			{
				$res_array['code'] = "CANCELED";
				$res_array['status'] = "CANCELED";
				$args->pay_status = "CANCELED";
			}

			executeQuery('hotopay.updatePurchaseStatus', $args);
			
			$_SESSION['hotopay_'.$vars->orderId] = (object) $res_array;

			$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
		}
	}

	/**
	 * Toss Payments에서 넘어오는 결제 Callback을 처리합니다
	 * 
	 * @return void
	 */
	public function procHotopayTossPaymentsCallback()
	{
		Context::setRequestMethod('JSON');
    	Context::setResponseMethod('JSON');

		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		
		if(!isset($vars->orderId) || !isset($vars->secret) || !isset($vars->status))
		{
			http_response_code(400);
			die(json_encode(array("status"=>"fail", "message"=>"parameter empty")));
		}
		$purchase_srl = (int)substr($vars->orderId, 2);

		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$purchase = executeQuery('hotopay.getPurchase', $args);
		if(!$purchase->toBool() || empty($purchase->data))
		{
			http_response_code(400);
			die(json_encode(array("status"=>"fail", "message"=>"unable to find purchase data")));
		}

		$pay_data = json_decode($purchase->data->pay_data);

		if(strcmp($pay_data->secret, $vars->secret) === 0) // 시크릿 키가 일치하면
		{
			if(strcmp($vars->status, "DONE") === 0) // 결제 성공일 경우
			{
				$this->_ActivePurchase($purchase_srl, $purchase->data->member_srl);

				$receipt_args = new stdClass();
				$receipt_args->purchase_srl = $purchase_srl;
				$receipt_args->receipt_url = $pay_data->receipt->url ?? "";
				executeQuery('hotopay.updatePurchaseReceiptUrl', $receipt_args);
			}
			else if(strcmp($vars->status, "CANCELED") === 0)
			{
				$args = new stdClass();
				$args->purchase_srl = $purchase_srl;
				$args->pay_status = "CANCELED";
				executeQuery('hotopay.updatePurchaseStatus', $args);
			}
		}else{
			http_response_code(400);
			die(json_encode(array("status"=>"fail", "message"=>"Key doesn't match")));
		}

		die();
	}

	/**
	 * 아임포트에서 넘어오는 결제 Callback을 처리합니다
	 * 
	 * @return void
	 */
	public function procHotopayIamportCallback()
	{
		Context::setRequestMethod('JSON');
		Context::setResponseMethod('JSON');

		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		$imp_uid = $vars->imp_uid;
		$merchant_uid = $vars->merchant_uid;
		$status = $vars->status;

		if (empty($imp_uid) || empty($merchant_uid) || empty($status)) {
			http_response_code(400);
			die(json_encode(array("status"=>"fail", "message"=>"parameter empty")));
		}
		$purchase_srl = (int)substr($merchant_uid, 2);

		$oHotopayModel = getModel('hotopay');
		$purchase = $oHotopayModel->getPurchase($purchase_srl);
		if (!$purchase->toBool() || empty($purchase))
		{
			http_response_code(400);
			die(json_encode(array("status"=>"fail", "message"=>"unable to find purchase data")));
		}

		// imp_uid 등록
		if (empty($purchase->iamport_uid))
		{
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->iamport_uid = $imp_uid;
			executeQuery('hotopay.updatePurchaseIamportUid', $args);
		}

		if ($status == 'paid')
		{
			if ($purchase->pay_status != "DONE")
			{
				$this->_ActivePurchase($purchase_srl, $purchase->member_srl);
			}

			// 영수증 데이터 등록
			$iamport = new Iamport();
			$imp_purchase = $iamport->getPaymentByImpUid($imp_uid);

			$receipt_args = new stdClass();
			$receipt_args->purchase_srl = $purchase_srl;
			$receipt_args->receipt_url = $imp_purchase->receipt_url ?? "";
			executeQuery('hotopay.updatePurchaseReceiptUrl', $receipt_args);
		}
		else if ($status == 'failed')
		{
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = "FAILED";
			executeQuery('hotopay.updatePurchaseStatus', $args);
		}
		else if ($status == 'cancelled')
		{
			$this->_RefundProcess($purchase_srl);
		}

		http_response_code(200);
		die(json_encode(array("status"=>"success", "message"=>"success")));
	}

	/**
	 * 결제가 완료되었다면 결제 완료 알림을 보내며, 상품 구매를 최종 승인합니다
	 * 
	 * @param int $purchase_srl 결제 번호를 받습니다.
	 * @param int $member_srl RX에서 멤버 번호를 받습니다.
	 * @return void
	 */
	public function _ActivePurchase($purchase_srl, $member_srl = -1)
	{
		$logged_info = Context::get('logged_info');
		if($member_srl == -1) $member_srl = $logged_info->member_srl;

		$oHotopayModel = getModel('hotopay');
		$purchase = $oHotopayModel->getPurchase($purchase_srl);
		
		$trigger_obj = new stdClass();
		$trigger_obj->member_srl = $member_srl;
		$trigger_obj->purchase_srl = $purchase_srl;
		$output = ModuleHandler::triggerCall('hotopay.activePurchase', 'before', $trigger_obj);
		if(!$output->toBool()) return $output;

		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$args->pay_status = "DONE";
		executeQuery('hotopay.updatePurchaseStatus', $args);
		
		$this->_MessageMailer("DONE", $purchase);
		$this->_AdminMailer("DONE", $purchase);

		$products = $oHotopayModel->getProductsByPurchaseSrl($purchase_srl);

		$oMemberController = getController('member');
		foreach($products as $product)
		{
			$group_srl = $product->product_buyer_group;
			if($group_srl != 0)
			{
				$oMemberController->addMemberToGroup($member_srl, $group_srl);
			}
		}

		$trigger_obj->group_srl = $group_srl;
		ModuleHandler::triggerCall('hotopay.activePurchase', 'after', $trigger_obj);
	}

	/**
	 * PG사와 통신하여 결제를 환불합니다.
	 * 
	 * @param int $purchase_srl 결제 번호입니다.
	 * @param string $cancel_reason 환불 사유입니다. 클라이언트에게 보여집니다.
	 * @param int $cancel_amount 환불 금액입니다. -1이면 전체환불, 0보다 크면 입력한 금액만큼 환불합니다.
	 * @param array $bank_info 환불 은행 정보입니다. 가상 계좌 결제에서만 사용됩니다.
	 * @return object
	 */
	public function _CancelPurchase($purchase_srl, $cancel_reason = 'Hotopay Refund', $cancel_amount = -1, $bank_info = array())
	{
		$oHotopayModel = getModel('hotopay');
		$purchase = $oHotopayModel->getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;
		if(empty($member_srl))
			return $this->createObject(-1, "member_srl을 찾을 수 없습니다.");

		switch($purchase->pay_method)
		{
			case 'card':
			case 'voucher':
			case 'cellphone':
				$tossController = new Toss();
				$output = $tossController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount);
				break;

			case 'v_account':
				$tossController = new Toss();
				$output = $tossController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount, $bank_info);
				break;

			case 'paypal':
				$paypalController = new Paypal();
				$output = $paypalController->cancelOrder($purchase_srl, $cancel_reason, $oHotopayModel->changeCurrency('KRW', 'USD', $cancel_amount));
				break;

			case 'kakaopay':
				$kakaoPayController = new KakaoPay();
				$output = $kakaoPayController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount);
				break;
			
			case 'n_account':
				$output = $this->createObject();
				break;
		}

		if($output->error == 0)
		{
			return $this->_RefundProcess($purchase_srl, $output->data);
		}
		else
		{
			return $output;
		}
	}

	/**
	 * Hotopay에서 결제 취소 처리를 합니다.
	 * 
	 * @param int $purchase_srl 결제 번호입니다.
	 * @param array $output_data PG사에서 받은 환불 정보입니다. pay_data에 json으로 저장됩니다.
	 * @return object
	 */
	public function _RefundProcess($purchase_srl, $output_data = [])
	{
		$oHotopayModel = getModel('hotopay');
		$purchase = $oHotopayModel->getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;

		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$args->pay_status = 'REFUNDED';
		$args->pay_data = json_encode($output_data);
		executeQuery('hotopay.updatePurchaseStatus', $args);
		executeQuery('hotopay.updatePurchaseData', $args);

		$products = $oHotopayModel->getProductsByPurchaseSrl($purchase_srl);

		foreach($products as $product)
		{
			$group_srl = $product->product_buyer_group;
			if($group_srl != 0)
			{
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->group_srl = $group_srl;
				$output = executeQuery('member.deleteMemberGroupMember', $args); // 그룹제거
			}
		}

		$oHotopayModel->rollbackOptionStock($purchase_srl);

		$oMemberController = getController('member');
		if(version_compare(__XE_VERSION__, '2.0.0', '<'))
		{
			$oMemberController->_clearMemberCache($member_srl); // for old rhymix
		}
		else
		{
			$oMemberController->clearMemberCache($member_srl);
		}

		$this->_MessageMailer("REFUNDED", $purchase);

		return $this->createObject();
	}

	/**
	 * 결제 상태에 따라서 알림을 보내주는 함수 입니다.
	 * 
	 * @param string $status 상태코드 입니다.
	 * @param object $purchase_data 결제 데이터입니다. DB에서 나온 데이터를 그대로 넣어주시면 됩니다.
	 * @return void
	 */
	public function _MessageMailer($status, $purchase_data)
	{
		if($purchase_data == null) return false;

		$config = $this->getConfig();
		$member_srl = $purchase_data->member_srl;
		$oHotopayModel = getModel('hotopay');

		switch($status)
		{
			case 'DONE':
				if(in_array(1, $config->purchase_success_notification_method))
				{
					// 쪽지 알림
					$oCommController = getController('communication');
					$oCommController->sendMessage(4, $member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_success_notification_message_note_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_success_notification_message_note, $purchase_data));
				}

				if(in_array(2, $config->purchase_success_notification_method))
				{
					// 메일 알림
					$this->_sendMail($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_success_notification_message_mail_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_success_notification_message_mail, $purchase_data));
				}

				if(in_array(3, $config->purchase_success_notification_method))
				{
					// SMS 알림
					$this->_sendSMS($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_success_notification_message_sms, $purchase_data));
				}
				break;

			case 'WAITING_FOR_DEPOSIT':
				if(in_array(1, $config->purchase_account_notification_method))
				{
					// 쪽지 알림
					$oCommController = getController('communication');
					$oCommController->sendMessage(4, $member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_account_notification_message_note_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_account_notification_message_note, $purchase_data));
				}

				if(in_array(2, $config->purchase_account_notification_method))
				{
					// 메일 알림
					$this->_sendMail($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_account_notification_message_mail_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_account_notification_message_mail, $purchase_data));
				}

				if(in_array(3, $config->purchase_account_notification_method))
				{
					// SMS 알림
					$this->_sendSMS($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_account_notification_message_sms, $purchase_data));
				}
				break;

			case 'REFUNDED':
				if(in_array(1, $config->purchase_refund_notification_method))
				{
					// 쪽지 알림
					$oCommController = getController('communication');
					$oCommController->sendMessage(4, $member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_refund_notification_message_note_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_refund_notification_message_note, $purchase_data));
				}

				if(in_array(2, $config->purchase_refund_notification_method))
				{
					// 메일 알림
					$this->_sendMail($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_refund_notification_message_mail_title, $purchase_data), $oHotopayModel->changeMessageRegisterKey($config->purchase_refund_notification_message_mail, $purchase_data));
				}

				if(in_array(3, $config->purchase_refund_notification_method))
				{
					// SMS 알림
					$this->_sendSMS($member_srl, $oHotopayModel->changeMessageRegisterKey($config->purchase_refund_notification_message_sms, $purchase_data));
				}
				break;
		}
	}

	/**
	 * 결제 상태에 따라 관리자에게 알림을 보내주는 함수입니다.
	 * 
	 * @param string $status 상태코드 입니다.
	 * @param object $purchase 결제 데이터입니다. DB에서 나온 데이터를 그대로 넣어주시면 됩니다.
	 * @return void
	 */
	public function _AdminMailer($status, $purchase)
	{
		$config = $this->getConfig();
		$member_srl = $purchase->member_srl;
		$oMemberModel = getModel('member');
		$oHotopayModel = getModel('hotopay');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		$price = number_format($purchase->product_purchase_price);
		$purchase_date = date("Y-m-d H:i:s", $purchase->regdate);
		$pay_method_korean = $oHotopayModel->purchaseMethodToString($purchase->pay_method);
		$purchase_title_substr = mb_substr($purchase->title, 0, 18);

		switch($status)
		{
			case "DONE":
				$message_body = "결제 완료 알림 메일입니다.<br><br>결제 코드: HT{$purchase->purchase_srl}<br>회원 닉네임: {$member_info->nick_name}<br>회원 이름: {$member_info->user_name}<br>결제 품목: {$purchase->title}<br>결제 금액: {$price}<br>결제 수단: {$pay_method_korean}<br>결제 시각: {$purchase_date}<br>";
				$this->_sendMail(4, "[HotoPay] 회원의 결제가 완료되었습니다.", $message_body);

				$sms_body = "[Hotopay] 결제알림 ({$pay_method_korean}/{$price}) {$purchase_title_substr}";
				$this->_sendSMS(4, $sms_body);
				break;
		}
	}

	/**
	 * 멤버 정보에 있는 메일 주소로 메일을 보내는 함수입니다.
	 * 
	 * @param int $member_srl 멤버 번호입니다.
	 * @param string $mail_title 메일 제목입니다.
	 * @param string $mail_content 메일 내용입니다.
	 * @return void
	 */
	public function _sendMail($member_srl, $mail_title, $mail_content)
	{
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);

		$oMail = new \Rhymix\Framework\Mail();
		$oMail->setSubject($mail_title);
		$oMail->setBody($mail_content);
		$oMail->addTo($member_info->email_address, $member_info->nick_name);
		$output = $oMail->send();

		return $output;
	}

	/**
	 * 멤버 정보에 있는 전화번호로 SMS를 보내는 함수입니다.
	 * 
	 * @param int $member_srl 멤버 번호입니다.
	 * @param string $content SMS 내용입니다.
	 * @return void
	 */
	public function _sendSMS($member_srl, $content)
	{
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);

		$oSmsHandler = new Rhymix\Framework\SMS();
		$phone_country = $member_info->phone_country;
		$phone_number = $member_info->phone_number;

		if(empty($phone_number))
		{
			return false;
		}

		// Sending SMS outside of Korea is currently not supported.
		if($phone_country !== 'KOR')
		{
			return false;
		}

		$phone_format = Rhymix\Framework\Korea::isValidPhoneNumber($phone_number);
		if($phone_format === false)
		{
			return false;
		}

		$oSmsHandler->addTo($phone_number);
		$oSmsHandler->setContent($content);
		$output = $oSmsHandler->send();

		return $output;
	}

	/**
	 * 멤버 메뉴에 목록을 추가합니다
	 * 
	 * @param object $obj member.getMemberMenu 트리거 데이터가 들어있습니다.
	 * @return void
	 */
	public function triggerAddMemberMenu($obj)
	{
		$logged_info = Context::get('logged_info');
		if ($logged_info->is_admin === 'Y')
		{
			$oMemberController = getController('member');
			$url = getUrl('', 'mid', 'hotopay', 'act', 'dispHotopayOrderList', 'target_member_srl', $obj->member_srl);
			$oMemberController->addMemberPopupMenu($url, '회원 구매 기록');
		}
	}

	/**
	 * 쇼핑몰 게시판에 글을 쓸 경우 상품을 등록합니다.
	 * 
	 * @param object $obj document.insertDocument 트리거 데이터가 들어있습니다.
	 * @return void
	 */
	public function triggerAfterInsertDocument($obj)
	{
		$config = $this->getConfig();
		$mid = $obj->mid;

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid($mid);
		if(in_array($module_info->module_srl, $config->board_module_srl))
		{
			if(!isset($obj->sale_option)) return;

			$lowest_price = -1;
			foreach($obj->sale_option as &$options)
			{
				$options = (object) $options;
				$options->price = preg_replace("/[^\d]/", "", $options->price) ?: 0;
				if($lowest_price == -1 || $options->price < $lowest_price)
				{
					$lowest_price = $options->price;
				}
			}

			$sale_price = $lowest_price;
			$extra_vars = (object) $obj->extra_vars ?: new \stdClass();
			$product_option = '';

			Context::set('product_name', $obj->title, true);
			Context::set('product_des', $obj->sub_title, true);
			Context::set('product_sale_price', $sale_price, true);
			Context::set('product_original_price', $sale_price, true);
			Context::set('product_option', $product_option, true);
			Context::set('product_buyer_group', 0, true);
			Context::set('extra_vars', $extra_vars, true);
			Context::set('document_srl', $obj->document_srl, true);

			$oHotopayAdminController = getAdminController('hotopay');
			$output = $oHotopayAdminController->procHotopayAdminInsertProduct();
		}
	}

	/**
	 * 쇼핑몰 게시판에 글을 쓸 경우 상품을 등록합니다.
	 * 
	 * @param object $obj document.insertDocument 트리거 데이터가 들어있습니다.
	 * @return void
	 */
	public function triggerAfterUpdateDocument($obj)
	{
		$config = $this->getConfig();
		$mid = $obj->mid;

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid($mid);
		if(in_array($module_info->module_srl, $config->board_module_srl))
		{
			if(!isset($obj->sale_option)) return;

			$lowest_price = -1;
			foreach($obj->sale_option as &$options)
			{
				$options = (object) $options;
				$options->price = preg_replace("/[^\d]/", "", $options->price); 
				if($lowest_price == -1 || $options->price < $lowest_price)
				{
					$lowest_price = $options->price;
				}
			}
			$logged_info = Context::get('logged_info');

			$sale_price = $lowest_price;

			$extra_vars = $obj->extra_vars ?? new stdClass();
			if(!($extra_vars instanceof stdClass))
			{
				$extra_vars = (object) $extra_vars ?? new stdClass();
			}
			$extra_vars->option_description = [];

			$product_option = '';

			Context::set('product_srl', $obj->hotopay_product_srl, true);
			Context::set('product_name', $obj->title, true);
			Context::set('product_des', $obj->sub_title, true);
			Context::set('product_sale_price', $sale_price, true);
			Context::set('product_original_price', $sale_price, true);
			Context::set('product_option', $product_option, true);
			Context::set('product_buyer_group', 0, true);
			Context::set('extra_vars', $extra_vars, true);
			$oHotopayAdminController = getAdminController('hotopay');
			$output = $oHotopayAdminController->procHotopayAdminModifyProduct();
		}
	}

	/**
	 * 카트에 상품을 넣습니다
	 * 
	 * @return void
	 */
	public function procHotopayAddCartItem()
	{
		Context::setResponseMethod('JSON');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		if(!$member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$config = $this->getConfig();
		$oHotopayModel = getModel('hotopay');
		$current_cart_item_count = $oHotopayModel->getCartItemCount($member_srl);
		if($current_cart_item_count >= $config->cart_item_limit)
		{
			return new BaseObject(-1, '장바구니에는 최대 ' . $config->cart_item_limit . '개의 상품만 담을 수 있습니다.');
		}

		$product_srl = Context::get('product_srl');
		$option_srl = Context::get('option_srl');
		$quantity = Context::get('quantity');

		$product_info = $oHotopayModel->getProduct($product_srl);
		if(!$product_info)
		{
			return new BaseObject(-1, '상품 정보가 없습니다.');
		}

		$option_info = $oHotopayModel->getOption($option_srl);
		if(!$option_info)
		{
			return new BaseObject(-1, '옵션 정보가 없습니다.');
		}

		$cart_items = $oHotopayModel->getCartItems($member_srl);
		foreach ($cart_items as $item)
		{
			if($item->product_srl == $product_srl && $item->option_srl == $option_srl)
			{
				return new BaseObject(-1, '이미 장바구니에 담겨있는 상품입니다.');
			}
		}

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->product_srl = $product_srl;
		$args->option_srl = $option_srl;
		$args->quantity = $quantity;
		$args->regdate = date('YmdHis');

		$oHotopayModel->insertCartItem($args);

		$this->setCache('cart_item_count_' . $member_srl, $current_cart_item_count + 1);

		$this->setMessage('장바구니에 추가되었습니다.');
	}

	/**
	 * 카트에서 선택한 상품을 제거합니다
	 * 
	 * @return void
	 */
	public function procHotopayDeleteCartItem()
	{
		Context::setResponseMethod('JSON');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		if(!$member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$cart_item_srl = Context::get('cart_item_srl');

		$oHotopayModel = getModel('hotopay');
		$oHotopayModel->deleteCartItem($cart_item_srl, $member_srl);

		$current_cart_item_count = $oHotopayModel->getCartItemCount($member_srl);
		$this->setCache('cart_item_count_' . $member_srl, $current_cart_item_count);

		$this->setMessage('장바구니에서 삭제되었습니다.');
	}

	/**
	 * 카트에 들어있는 상품의 세부 값을 변경합니다
	 * 
	 * @return void
	 */
	public function procHotopayUpdateCartItem()
	{
		Context::setResponseMethod('JSON');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		if(!$member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$cart_item_srl = Context::get('cart_item_srl');
		$option_srl = Context::get('option_srl');
		$quantity = Context::get('quantity');

		$args = new stdClass();
		$args->cart_item_srl = $cart_item_srl;
		$args->member_srl = $member_srl;
		$args->option_srl = $option_srl;
		$args->quantity = $quantity;
		$args->regdate = date('YmdHis');

		$oHotopayModel = getModel('hotopay');
		$oHotopayModel->updateCartItem($args);

		$this->setMessage('장바구니가 수정되었습니다.');
	}
}
