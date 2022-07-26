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
		// if($logged_info->member_srl != 4) return $this->createObject(-1, "현재 결제 기능 점검중입니다. 잠시 뒤에 다시 시도 해주세요.");

		$oHotopayModel = getModel('hotopay');
		$order_id = getNextSequence();

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $logged_info->member_srl;

		$product_list = [];
		$option_list = [];
		foreach ($vars->purchase_info as $product)
		{
			$product_list[] = $product['product_srl'];
			$option_list[$product['product_srl']] = $product['option_srl'];

			if(!isset($product['option_srl'])) return $this->createObject(-1, "유효한 옵션을 선택해주세요");
		}

		$product_list = $oHotopayModel->getProducts($product_list);

		$title = "";
		$tc = -1;
		$total_price = 0;
		$original_price = 0;

		foreach($product_list as $product)
		{
			if($tc < 0)
			{
				$title = $product->product_name;
				$tc++;
			}

			$option_srl = $option_list[$product->product_srl];
			$option = $product->product_option[$option_srl];
			$total_price += $option->price;
			$original_price += $option->price;

			$obj = new StdClass();
			$obj->item_srl = getNextSequence();
			$obj->purchase_srl = $order_id;
			$obj->product_srl = $product->product_srl;
			$obj->option_srl = $option_list[$product->product_srl];
			$obj->option_name = $option->title;
			$obj->purchase_price = $option->price;
			$obj->original_price = $option->price;
			$obj->extra_vars = serialize($option->extra_vars ?: new stdClass());
			$obj->regdate = time();
			executeQuery('hotopay.insertPurchaseItem', $obj);
		}

		if($tc > 0)
		{
			$title .= " 외 ".$tc."개";
		}

		$args->products = json_encode(array("t"=>$title)); // 구시대의 유물.. 미안합니다 ㅜㅜ
		$args->pay_method = $vars->pay_method;
		$args->product_purchase_price = $total_price;
		$args->product_original_price = $original_price;
		$args->pay_status = "PENDING";
		$args->regdate = time();
		$args->pay_data = '';
		$args->extra_vars = serialize($vars->extra_vars ?? new stdClass());

		switch($vars->pay_method)
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

			case "n_account":
				$order_obj = new stdClass();
				$order_obj->depositor_name = $vars->depositor_name ?: mb_substr(($logged_info->user_name ?: ('구매자'.rand(100, 999))), 0, 6);
				$args->pay_data = json_encode($order_obj);
				break;
		}

		executeQuery("hotopay.insertPurchase", $args);

		$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayPayProcess','order_id',$order_id));
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

		if(strcmp($vars->pay_status, "success") === 0) // 결제 성공
		{
			$purchase_srl = (int)substr($vars->order_id, 2);

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

			$purchase_data = json_decode($purchase->data->products);

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
					$args->purchase_srl = (int)substr($vars->orderId, 2);
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

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
				}

				if($purchase->data->pay_method == 'v_account')
				{
					if($response_json->status == 'WAITING_FOR_DEPOSIT')
					{
						$this->_MessageMailer("WAITING_FOR_DEPOSIT", $purchase->data);
					}
				}

				$response_json->p_status = "success";
				$response_json->product_title = $purchase_data->t;
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
					$args->purchase_srl = (int)substr($vars->order_id, 2);
					$args->pay_data = json_encode($capture_output);
					executeQuery('hotopay.updatePurchaseData', $args);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $args->purchase_srl;
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
					$args->purchase_srl = (int)substr($vars->orderId, 2);
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $args->purchase_srl;
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
				$order_detail->product_title = $purchase_data->t;
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
					$args->purchase_srl = (int)substr($vars->order_id, 2);
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$trigger_obj = new stdClass();
					$trigger_obj->purchase_srl = $args->purchase_srl;
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
				$trigger_obj->purchase_srl = (int)substr($vars->order_id, 2);
				$trigger_obj->pay_status = "DONE";
				$trigger_obj->pay_data = $response_json;
				$trigger_obj->pay_pg = "kakao";
				$trigger_obj->amount = $vars->amount;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$response_json->method = 'kakaopay';
				$response_json->p_status = "success";
				$response_json->product_title = $purchase_data->t;
				$response_json->orderId = $vars->order_id;
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
				$order_detail->product_title = $purchase_data->t;

				$_SESSION['hotopay_'.$vars->order_id] = $order_detail;

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = (int)substr($vars->order_id, 2);
				$trigger_obj->pay_status = "WAITING_FOR_DEPOSIT";
				$trigger_obj->pay_data = $order_detail;
				$trigger_obj->pay_pg = "n_account";
				$trigger_obj->amount = $order_detail->totalAmount;
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
			$args->purchase_srl = (int)substr($order_id, 2);
			$args->pay_status = "FAILED";

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

		$trigger_obj = new stdClass();
		$trigger_obj->member_srl = $member_srl;
		$trigger_obj->purchase_srl = $purchase_srl;
		$trigger_obj->group_srl = $group_srl;
		ModuleHandler::triggerCall('hotopay.activePurchase', 'after', $trigger_obj);
	}

	/**
	 * 결제를 환불합니다.
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
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = 'REFUNDED';
			$args->pay_data = json_encode($output->data);
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
		else
		{
			return $output;
		}
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
		$purchase_data = json_decode($purchase->products);
		$price = number_format($purchase->product_purchase_price);
		$purchase_date = date("Y-m-d H:i:s", $purchase->regdate);
		$pay_method_korean = $oHotopayModel->purchaseMethodToString($purchase->pay_method);
		$purchase_title_substr = mb_substr($purchase_data->t, 0, 18);

		switch($status)
		{
			case "DONE":
				$message_body = "결제 완료 알림 메일입니다.<br><br>결제 코드: HT{$purchase->purchase_srl}<br>회원 닉네임: {$member_info->nick_name}<br>회원 이름: {$member_info->user_name}<br>결제 품목: {$purchase_data->t}<br>결제 금액: {$price}<br>결제 수단: {$pay_method_korean}<br>결제 시각: {$purchase_date}<br>";
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
		$oMemberController = getController('member');
		$url = getUrl('', 'mid', 'hotopay', 'act', 'dispHotopayOrderList', 'target_member_srl', $obj->member_srl);
		$oMemberController->addMemberPopupMenu($url, '회원 구매 기록');
	}
}
