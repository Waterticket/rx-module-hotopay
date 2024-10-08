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

		if (empty($vars->pay_method))
		{
			return $this->createObject(-1, "결제 수단을 선택해주세요.");
		}

		$oHotopayModel = HotopayModel::getInstance();

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

		$product_info = HotopayModel::getProducts($product_srl_list);

		$is_non_billing_product_exist = false;
		$is_billing_product_exist = false;
		$point_discount_allow = ($config->point_discount == 'Y');
		foreach ($product_info as $product)
		{
			if ($product->is_billing == 'Y')
			{
				$is_billing_product_exist = true;
				$point_discount_allow = false;
			}
			else
			{
				$is_non_billing_product_exist = true;
			}

			if ($product->allow_use_point != 'Y')
			{
				$point_discount_allow = false;
			}
		}

		if ($is_billing_product_exist && $is_non_billing_product_exist)
		{
			return $this->createObject(-1, '정기결제 상품과 일반결제 상품은 별도로 결제해주세요.');
		}

		if ($is_billing_product_exist)
		{
			$validator = new HotopayLicenseValidator();
			$isLicenseValid = $validator->validate($config->hotopay_license_key);
			if (!$isLicenseValid)
			{
				return $this->createObject(-1, '결제를 진행할 수 없습니다. 관리자에게 문의해주세요.');
			}

			if ($vars->use_point > 0)
			{
				return $this->createObject(-1, '정기결제 상품은 포인트로 결제할 수 없습니다.');
			}
		}

		$order_id = getNextSequence();

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $logged_info->member_srl;
		$args->is_billing = $is_billing_product_exist ? 'Y' : 'N';

		foreach ($product_list as $product)
		{
			foreach ($product_info as $info)
			{
				if($product->product_srl == $info->product_srl)
				{
					$product->info = $info;
					break;
				}
			}

			if (!isset($product->info) || empty($product->info))
			{
				return $this->createObject(-1, '상품 정보를 가져오는데 실패했습니다.');
			}

			$product_option_srls = array();
			foreach ($product->info->product_option as $option)
			{
				$product_option_srls[] = $option->option_srl;
			}

			if (!in_array($product->option_srl, $product_option_srls))
			{
				return $this->createObject(-1, '유효한 옵션을 선택해주세요');
			}
		}

		$title = "";
		$tc = -1;
		$total_price = 0;
		$original_price = 0;

		foreach($product_list as $_product)
		{
			if($_product->info->product_status != 'selling') return $this->createObject(-1, "판매중이 아닌 상품이 있습니다.");

			$option_srl = $_product->option_srl;
			$option = $_product->info->product_option[$option_srl];

			if($option->infinity_stock != 'Y' && $option->stock < 1) return $this->createObject(-1, "재고가 부족한 항목이 있습니다.");
		}

		$vars->hotopay_extra_info = (array) $vars->hotopay_extra_info;
		$extra_info_list_obj = HotopayModel::getProductExtraInfo(array_merge($product_srl_list, array(0)));
		$extra_info_list = [];
		foreach ($extra_info_list_obj as $extra_info)
		{
			$extra_info_list[] = $extra_info->key_name;
		}

		foreach ($extra_info_list_obj as $extra_info)
		{
			if ($extra_info->required == 'Y' && empty($vars->hotopay_extra_info[$extra_info->key_name]))
			{
				return $this->createObject(-1, $extra_info->title . '을(를) 입력해주세요.');
			}
		}

		foreach ($vars->hotopay_extra_info as $key => $extra_info)
		{
			if (!in_array($key, $extra_info_list)) continue;
			if (is_array($extra_info)) $extra_info = implode(',', $extra_info);

			$extra_info_args = new stdClass();
			$extra_info_args->info_srl = getNextSequence();
			$extra_info_args->purchase_srl = $order_id;
			$extra_info_args->key_name = $key;
			$extra_info_args->value = $extra_info;
			$extra_info_args->regdate = date('Y-m-d H:i:s');
			HotopayModel::insertPurchaseExtraInfo($extra_info_args);
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
			$obj->subscription_srl = 0;
			$obj->extra_vars = serialize($option->extra_vars ?: new stdClass());
			$obj->regdate = time();
			executeQuery('hotopay.insertPurchaseItem', $obj);

			$total_price += $obj->purchase_price;
			$original_price += $obj->original_price;

			if($option->infinity_stock != 'Y')
			{
				HotopayModel::minusOptionStock($option_srl, 1);
			}
		}

		if($tc > 0)
		{
			$title .= " 외 ".$tc."개";
		}

		$extra_vars = new stdClass(); // $vars->extra_vars ?? new stdClass();
		$extra_vars->use_point = 0;

		$input_point = intval($vars->use_point, 10) ?? 0;
		if ($config->point_discount == 'Y' && $point_discount_allow)
		{
			$user_point = PointModel::getPoint($logged_info->member_srl, true);

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
			$oPointController = PointController::getInstance();
			$oPointController->setPoint($logged_info->member_srl, $input_point, 'minus');

			$total_price -= $input_point;
			$extra_vars->use_point = $input_point;
		}
		else if ($input_point > 0)
		{
			return $this->createObject(-1, "포인트 사용이 불가능합니다.");
		}

		if ($total_price <= 0) $vars->pay_method = 'point';

		if (str_starts_with($vars->pay_method, 'billing_key_'))
		{
			$key_idx = intval(substr($vars->pay_method, 12));
			$key = HotopayModel::getBillingKey($key_idx);
			if (!$key->key)
			{
				return $this->createObject(-1, "결제 정보가 올바르지 않습니다.");
			}

			if ($key->member_srl != $logged_info->member_srl)
			{
				return $this->createObject(-1, "결제 정보가 올바르지 않습니다.");
			}

			if ($key->pg == 'toss')
			{
				$vars->pay_method = 'toss';
			}
			else if ($key->pg == 'payple')
			{
				$vars->pay_method = 'paypl_' . $key->payment_type;
			}

			$key->key = $oHotopayModel->decryptKey($key->key);
			$_SESSION['hotopay_billing_key'] = $key;
		}

		$args->title = $title;
		$args->products = json_encode(array("t"=>$title)); // 구시대의 유물
		$args->pay_method = $vars->pay_method;
		$args->product_purchase_price = $total_price;
		$args->product_original_price = $original_price;
		$args->used_point = $input_point;
		$args->pay_status = "PENDING";
		$args->regdate = time();
		$args->pay_data = '';
		$args->extra_vars = serialize($extra_vars);
		$args->reward_point = round($config->purchase_reward_point_percent * $total_price);

		$pg = in_array($vars->pg, ['toss']) ? $vars->pg : $vars->pay_method;
		if(substr($pg, 0, 6) === 'paypl_')
		{
			$pg = 'payple';
		}

		switch($pg)
		{
			case 'paypal':
				$usd_total = HotopayModel::changeCurrency('KRW', 'USD', $total_price);

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

		$output = executeQuery("hotopay.insertPurchase", $args);
		if (!$output->toBool())
		{
			return $output;
		}

		if (Context::getRequestMethod() == 'JSON')
		{
			$order_id_str = 'HT'.str_pad($order_id, 4, "0", STR_PAD_LEFT);
			$this->add('order_id', $order_id_str);
			$this->add('order_name', $args->title);

			if ($pg == 'toss')
			{
				$this->add('success_url', '/hotopay/payStatus/toss/success/'.$order_id_str);
				$this->add('fail_url', '/hotopay/payStatus/toss/fail/'.$order_id_str);
			}
		}
		else
		{
			header('HTTP/1.1 307 Temporary move');
			header('Location: ' . getNotEncodedUrl('','mid','hotopay','act','procHotopayPayProcess','order_id',$order_id));
			return;
		}
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

			case 'toss_paypal':
				$purchase_data->pay_method_korean = '해외간편결제';
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
		$oHotopayModel = HotopayModel::getInstance();

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

			if ($purchase->data->is_billing == 'Y')
			{
				$validator = new HotopayLicenseValidator();
				$isLicenseValid = $validator->validate($config->hotopay_license_key);
				if (!$isLicenseValid)
				{
					return $this->createObject(-1, '결제를 진행할 수 없습니다. 관리자에게 문의해주세요.');
				}
			}

			if(strcmp($vars->pay_pg, "tossbill") === 0) // Toss 정기결제 키 등록 처리
			{
				if($purchase->data->is_billing != 'Y')
				{
					return $this->createObject(-1, "결제 실패. (code: 1012)");
				}

				$tossController = new Toss(true);
				if (!isset($_SESSION['hotopay_billing_key']))
				{
					if (empty($vars->customerKey) || empty($vars->authKey))
					{
						return $this->createObject(-1, "결제 실패. (code: 1013)");
					}

					$output = $tossController->requestBillingKey($vars->customerKey, $vars->authKey);
					if (!$output->toBool())
					{
						return $this->createObject(-1, "결제 실패. ".($output->data->message ?? '')." (code: 1014)");
					}

					$billingKeyObject = $output->data;
					$key_hash = strtoupper(hash('sha256', $purchase->data->member_srl . $billingKeyObject->billingKey));
					if ($purchase->data->pay_method == 'card')
					{
						$key_hash = strtoupper(hash('sha256', $purchase->data->member_srl . $billingKeyObject->card->number));
					}
					$key = HotopayModel::getBillingKeyByKeyNumber($purchase->data->member_srl, $billingKeyObject->card->number);
					$key_idx = $key->key_idx ?? 0;
					if ($key_idx <= 0)
					{
						$key = new stdClass();
						$key->key_idx = getNextSequence();
						$key->member_srl = $purchase->data->member_srl;
						$key->pg = 'toss';
						$key->type = 'billing';
						$key->key = $oHotopayModel->encryptKey($billingKeyObject->billingKey);
						$key->key_hash = $key_hash;
						$key->regdate = time();

						switch ($purchase->data->pay_method)
						{
							case 'card':
								$key->payment_type = 'card';
								$key->alias = ($billingKeyObject->card->ownerType . $billingKeyObject->card->cardType) ?: 'CARD';
								$key->number = $billingKeyObject->card->number ?? '0000********0000';
								break;

							default:
								return $this->createObject(-1, "결제 실패. (code: 1015)");
						}

						HotopayModel::insertBillingKey($key);
						$key_idx = $key->key_idx;
					}
					else
					{
						$key_update_obj = new stdClass();
						$key_update_obj->key_idx = $key_idx;
						$key_update_obj->key = $oHotopayModel->encryptKey($billingKeyObject->billingKey);
						$key_update_obj->key_hash = $key_hash;
						HotopayModel::updateBillingKey($key_update_obj);
					}
				}
				else
				{
					$key = $_SESSION['hotopay_billing_key'];
					unset($_SESSION['hotopay_billing_key']);
					$key_idx = $key->key_idx;

					$key = HotopayModel::getBillingKey($key_idx);
					if (!$key->key_idx)
					{
						return $this->createObject(-1, "결제 실패. (code: 1017)");
					}

					if ($key->member_srl != $purchase->data->member_srl)
					{
						return $this->createObject(-1, "결제 실패. (code: 1018)");
					}
				}

				$subscription = new stdClass();
				$subscription->member_srl = $this->user->member_srl;
				$subscription->billing_key_idx = $key_idx;
				$subscription->register_date = date('Y-m-d H:i:s');
				$subscription->last_billing_date = '0000-00-00 00:00:00';

				$items = HotopayModel::getPurchaseItems($purchase_srl);
				$_options = HotopayModel::getOptionsByPurchaseSrl($purchase_srl);
				$print_esti_billing_date = '';
				$billingTotal = [];
				foreach ($items as $item)
				{
					$subscription->subscription_srl = getNextSequence();
					$subscription->product_srl = $item->product_srl;
					$subscription->option_srl = $item->option_srl;
					$subscription->quantity = $item->quantity;
					$subscription->price = $item->purchase_price;
					$subscription->period = -1;
					$subscription->item_name = sprintf("%s #%d", $purchase->data->title, $subscription->subscription_srl);

					foreach ($_options as $_option)
					{
						if ($_option->option_srl == $item->option_srl)
						{
							$subscription->period = $_option->billing_period_date;
							break;
						}
					}

					if ($subscription->period < 0)
					{
						return new BaseObject(-1, "INVALID_PERIOD 관리자에게 문의하세요");
					}

					$subscription->esti_billing_date = date('Y-m-d H:i:s');
					HotopayModel::insertSubscription($subscription);
					HotopayModel::updatePurchaseItemSubscriptionSrl($item->item_srl, $subscription->subscription_srl);

					$billingStatus = $tossController->requestBilling($subscription);
					if (!$billingStatus->toBool())
					{
						$_SESSION['hotopay_'.$vars->order_id] = array(
							"p_status" => "failed",
							"orderId" => $vars->order_id,
							"code" => $billingStatus->data->message ?? 'TOSS_BILLING_FAILED',
							"message" => "결제를 실패하였습니다. (code: 1016)"
						);
	
						$subscription_update_obj = new stdClass();
						$subscription_update_obj->subscription_srl = $subscription->subscription_srl;
						$subscription_update_obj->status = "FAILED_INITIAL";
						HotopayModel::updateSubscription($subscription_update_obj);

						HotopayModel::rollbackOptionStock($purchase_srl);
						$this->refundUsedPoint($purchase_srl);

						$args->pay_data = json_encode($billingStatus->data);
						$args->pay_status = "FAILED";
						executeQuery('hotopay.updatePurchaseStatus', $args);
						executeQuery('hotopay.updatePurchaseData', $args);
	
						$trigger_obj = new stdClass();
						$trigger_obj->purchase_srl = $purchase_srl;
						$trigger_obj->pay_status = "FAILED";
						$trigger_obj->pay_data = $billingStatus->data;
						$trigger_obj->pay_pg = "inicis";
						$trigger_obj->amount = $item->purchase_price;
						ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);
	
						$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
						return;
					}

					$oDB = DB::getInstance();
					$esti_billing_date = date("Y-m-d H:i:s", strtotime("+" . $subscription->period . " days"));
					$last_billing_date = date("Y-m-d H:i:s");
					$stmt = $oDB->prepare("UPDATE hotopay_subscription AS subscription SET subscription.esti_billing_date = :esti_billing_date, subscription.last_billing_date = :last_billing_date WHERE subscription.subscription_srl = :subscription_srl");
					$stmt->bindValue(":subscription_srl", $subscription->subscription_srl);
					$stmt->bindValue(":esti_billing_date", $esti_billing_date);
					$stmt->bindValue(":last_billing_date", $last_billing_date);
					$stmt->execute();

					$print_esti_billing_date = date("Y-m-d", strtotime("+" . $subscription->period . " days"));
					$billingTotal[] = $billingStatus->data;

					HotopayModel::copyPurchaseExtraInfo($subscription->subscription_srl, $purchase_srl);
				}

				$args->pay_data = json_encode($billingTotal);
				$args->pay_status = "DONE";
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);
				$this->_ActivePurchase($purchase_srl);

				$trigger_obj = new stdClass();
				$trigger_obj->purchase_srl = $purchase_srl;
				$trigger_obj->pay_status = $args->pay_status;
				$trigger_obj->pay_data = $billingTotal;
				$trigger_obj->pay_pg = "tossbill";
				$trigger_obj->amount = $purchase->data->product_purchase_price;
				ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);

				$response_json = new stdClass();
				$response_json->method = 'tossbill';
				$response_json->p_status = "success";
				$response_json->product_title = $purchase->data->title;
				$response_json->orderId = $vars->order_id;
				$response_json->pay_status = $args->pay_status;
				$response_json->pay_data = $billingTotal;
				$response_json->amount = $purchase->data->product_purchase_price;
				$response_json->payment = $key->alias;
				$response_json->esti_billing_date = $print_esti_billing_date;
				$_SESSION['hotopay_'.$vars->order_id] = $response_json;
				$this->setRedirectUrl(getNotEncodedUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
				return;
			}
			else if(strcmp($vars->pay_pg, "toss") === 0) // Toss 처리
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

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

				$PCD_PAYER_ID = $vars->PCD_PAYER_ID;
				$vars->PCD_PAYER_ID = '*** secret ***';
				$args->pay_data = json_encode($vars);
				if ($vars->PCD_PAY_RST == 'success' && str_contains($vars->PCD_PAY_CODE, '0000'))
				{
					$args->pay_status = "DONE";
				}
				else
				{
					$args->pay_status = "FAILED";
				}
				executeQuery('hotopay.updatePurchaseStatus', $args);
				executeQuery('hotopay.updatePurchaseData', $args);

				$error_message = $vars->PCD_PAY_MSG;
				if($args->pay_status == "DONE") // 결제 완료에 경우
				{
					$payple = new Payple();
					$result = $payple->confirmPaywork($vars, $purchase->data, $PCD_PAYER_ID);
					if (!$result->toBool())
					{
						$args->pay_status = "FAILED";
						executeQuery('hotopay.updatePurchaseStatus', $args);
						$error_message = $result->message;
					}
					else
					{
						$result = $result->data;

						$this->_ActivePurchase($purchase_srl);
						$receipt_args = new stdClass();
						$receipt_args->purchase_srl = $purchase_srl;
						$receipt_args->receipt_url = $result->PCD_PAY_CARDRECEIPT ?? "";
						executeQuery('hotopay.updatePurchaseReceiptUrl', $receipt_args);

						$key_idx = 0;
						if ($config->payple_purchase_type == 'password' || $purchase->data->is_billing == 'Y')
						{
							$validator = new HotopayLicenseValidator();
							$isLicenseValid = $validator->validate($config->hotopay_license_key);
							if (!$isLicenseValid)
							{
								return $this->createObject(-1, '결제를 진행할 수 없습니다. 관리자에게 문의해주세요.');
							}

							if (isset($_SESSION['hotopay_billing_key']))
							{
								$before_idx = $_SESSION['hotopay_billing_key']->key_idx;
								unset($_SESSION['hotopay_billing_key']);

								$key_idx = $before_idx;

								$key = HotopayModel::getBillingKey($before_idx);
								$calculated_key_hash = strtoupper(hash('sha256', $this->user->member_srl . $result->PCD_PAYER_ID));
								if (!empty($key->key) && $key->key_hash != $calculated_key_hash)
								{
									// update
									$billing_key_obj = new stdClass();
									$billing_key_obj->key_idx = $before_idx;
									$billing_key_obj->key = $oHotopayModel->encryptKey($result->PCD_PAYER_ID);
									$billing_key_obj->key_hash = $calculated_key_hash;
									switch ($result->PCD_PAY_TYPE)
									{
										case 'transfer':
											$billing_key_obj->payment_type = 'transfer';
											$billing_key_obj->alias = $result->PCD_PAY_BANKNAME;
											$billing_key_obj->number = $result->PCD_PAY_BANKNUM ?? '0000*******0000';
											break;

										case 'card':
										default:
											$billing_key_obj->payment_type = 'card';
											$billing_key_obj->alias = $result->PCD_PAY_CARDNAME;
											$billing_key_obj->number = $result->PCD_PAY_CARDNUM ?? '0000-****-****-0000';
											break;
									}

									HotopayModel::updateBillingKey($billing_key_obj);
								}
							}
							else
							{
								$key_hash = strtoupper(hash('sha256', $purchase->data->member_srl . $result->PCD_PAYER_ID));
								$key = HotopayModel::getBillingKeyByKeyHash($purchase->data->member_srl, $key_hash);
								if (!$key->key_idx)
								{
									$billing_key_obj = new stdClass();
									$billing_key_obj->key_idx = getNextSequence();
									$billing_key_obj->member_srl = $purchase->data->member_srl;
									$billing_key_obj->pg = 'payple';
									$billing_key_obj->type = 'password';
									$billing_key_obj->key = $oHotopayModel->encryptKey($result->PCD_PAYER_ID);
									$billing_key_obj->key_hash = $key_hash;
									$billing_key_obj->regdate = time();

									if ($purchase->data->is_billing == 'Y')
									{
										$billing_key_obj->type = 'billing';
									}

									switch ($result->PCD_PAY_TYPE)
									{
										case 'transfer':
											$billing_key_obj->payment_type = 'transfer';
											$billing_key_obj->alias = $result->PCD_PAY_BANKNAME ?? 'BANK';
											$billing_key_obj->number = $result->PCD_PAY_BANKNUM ?? '0000*******0000';
											break;

										case 'card':
										default:
											$billing_key_obj->payment_type = 'card';
											$billing_key_obj->alias = $result->PCD_PAY_CARDNAME ?? 'CARD';
											$billing_key_obj->number = $result->PCD_PAY_CARDNUM ?? '0000-****-****-0000';
											break;
									}

									HotopayModel::insertBillingKey($billing_key_obj);
									$key_idx = $billing_key_obj->key_idx;
								}
							}
						}

						if ($purchase->data->is_billing == 'Y')
						{
							$subscription = new stdClass();
							$subscription->member_srl = $this->user->member_srl;
							$subscription->billing_key_idx = $key_idx;
							$subscription->register_date = date('Y-m-d H:i:s');
							$subscription->last_billing_date = date('Y-m-d H:i:s');

							$items = HotopayModel::getPurchaseItems($purchase_srl);
							$_options = HotopayModel::getOptionsByPurchaseSrl($purchase_srl);
							foreach ($items as $item)
							{
								$subscription->subscription_srl = getNextSequence();
								$subscription->product_srl = $item->product_srl;
								$subscription->option_srl = $item->option_srl;
								$subscription->quantity = $item->quantity;
								$subscription->price = $item->purchase_price;
								$subscription->period = -1;

								foreach ($_options as $_option)
								{
									if ($_option->option_srl == $item->option_srl)
									{
										$subscription->period = $_option->billing_period_date;
										break;
									}
								}

								if ($subscription->period < 0)
								{
									return new BaseObject(-1, "INVALID_PERIOD 관리자에게 문의하세요");
								}

								$subscription->esti_billing_date = date('Y-m-d H:i:s', strtotime('+'.$subscription->period.' days'));
								HotopayModel::insertSubscription($subscription);
								HotopayModel::copyPurchaseExtraInfo($subscription->subscription_srl, $purchase_srl);
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
						"message" => "결제를 실패하였습니다. ".$error_message." (code: 1011)"
					);

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

					HotopayModel::rollbackOptionStock($purchase_srl);
					$this->refundUsedPoint($purchase_srl);

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

				$this->_ActivePurchase($purchase_srl);
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

			$purchase = HotopayModel::getPurchase($purchase_srl);
			if(!in_array($purchase->pay_status, ['WAITING_FOR_DEPOSIT', 'PENDING']))
			{
				return $this->createObject(-1, "잘못된 접근입니다.");
			}

			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = "FAILED";

			HotopayModel::rollbackOptionStock($purchase_srl);
			$this->refundUsedPoint($purchase_srl);

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
			else if(strcmp($vars->status, "EXPIRED") === 0)
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

	public function procHotopayTossPaymentsCallbackStatusChanged()
	{
		Context::setRequestMethod('JSON');
    	Context::setResponseMethod('JSON');

		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		$eventType = $vars->eventType;
		if ($eventType != "PAYMENT_STATUS_CHANGED") {
			die(json_encode(array("status"=>"success", "message"=>"pass event type")));
		}

		$data = $vars->data;
		$status = $data->status;
		if (!in_array($status, array("EXPIRED", "CANCELED", "PARTIAL_CANCELED"))) {
			die(json_encode(array("status"=>"success", "message"=>"pass status")));
		}

		$orderId = $data->orderId;
		$purchase_srl = (int)substr($orderId, 2);

		$purchase = HotopayModel::getPurchase($purchase_srl);
		if(!$purchase->purchase_srl)
		{
			http_response_code(400);
			die(json_encode(array("status"=>"failed", "message"=>"unable to find purchase data")));
		}

		if ($purchase->product_purchase_price != $data->totalAmount)
		{
			http_response_code(400);
			die(json_encode(array("status"=>"failed", "message"=>"price doesn't match")));
		}

		$pay_data = json_decode($purchase->pay_data);
		if (!is_array($pay_data))
		{
			$pay_data = array($pay_data);
		}

		$paymentKeyEqual = false;
		foreach ($pay_data as $value) {
			if ($value->paymentKey == $data->paymentKey)
			{
				$paymentKeyEqual = true;
				break;
			}
		}

		if (!$paymentKeyEqual)
		{
			http_response_code(400);
			die(json_encode(array("status"=>"failed", "message"=>"payment key doesn't match")));
		}

		switch($status)
		{
			case "EXPIRED":
				$args = new stdClass();
				$args->purchase_srl = $purchase_srl;
				$args->pay_status = "EXPIRED";
				executeQuery('hotopay.updatePurchaseStatus', $args);
				break;

			case "CANCELED":
			case "PARTIAL_CANCELED":
				$this->_RefundProcess($purchase_srl, $data);
				break;
		}

		http_response_code(200);
		die(json_encode(array("status"=>"success", "message"=>"success")));
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

		$purchase = HotopayModel::getPurchase($purchase_srl);
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

	public function procHotopayPaypleCallback()
	{
		Context::setRequestMethod('JSON');
		Context::setResponseMethod('JSON');

		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		$oHotopayModel = HotopayModel::getInstance();

		if ($vars->PCD_PAY_OID)
		{
			if ($vars->PCD_PAY_RST != 'success' || !str_contains($vars->PCD_PAY_CODE, "0000"))
			{
				return new BaseObject();
			}

			$purchase_srl = (int) substr($vars->PCD_PAY_OID, 2);
			$purchase = HotopayModel::getPurchase($purchase_srl);

			if ($vars->PCD_REFUND_TOTAL)
			{
				// 결제 취소
				if (!in_array($purchase->status, ['CANCELED', 'FAILED', 'REFUNDED', 'REFUNDING']))
				{
					$args = new stdClass();
					$args->purchase_srl = $purchase_srl;
					$args->pay_status = 'REFUNDED';
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$this->_RefundProcess($purchase_srl, $vars);
				}
			}
			else
			{
				// 결제 성공
				// if ($purchase->pay_status != "DONE")
				// {
				// 	$this->_ActivePurchase($purchase_srl, $purchase->member_srl);
				// }

				// Payple은 CERT 방식으로 결제하기 때문에 Webhook으로 결제 성공 처리를 할 필요가 없음
			}
		}
		else
		{
			$member_srl = $vars->PCD_PAYER_NO;
			$payer_id = $vars->PCD_PAYER_ID;
			if (!$member_srl || !$payer_id)
			{
				return new BaseObject(-1, 'msg_invalid_request');
			}

			if ($vars->PCD_AUTH_KEY)
			{
				// 카드/계좌 등록
				$key_hash = strtoupper(hash('sha256', $member_srl . $payer_id));
				$key = HotopayModel::getBillingKeyByKeyHash($member_srl, $key_hash);
				if (!$key->key_idx)
				{
					$billing_key_obj = new stdClass();
					$billing_key_obj->key_idx = getNextSequence();
					$billing_key_obj->member_srl = $member_srl;
					$billing_key_obj->pg = 'payple';
					$billing_key_obj->type = $config->payple_purchase_type ?? 'password';
					$billing_key_obj->key = $oHotopayModel->encryptKey($payer_id);
					$billing_key_obj->key_hash = $key_hash;
					$billing_key_obj->regdate = time();

					switch ($vars->PCD_PAY_TYPE)
					{
						case 'transfer':
							$billing_key_obj->payment_type = 'transfer';
							$billing_key_obj->alias = $vars->PCD_PAY_BANKNAME ?? 'BANK';
							$billing_key_obj->number = $vars->PCD_PAY_BANKNUM ?? '0000*******0000';
							break;

						case 'card':
						default:
							$billing_key_obj->payment_type = 'card';
							$billing_key_obj->alias = $vars->PCD_PAY_CARDNAME ?? 'CARD';
							$billing_key_obj->number = $vars->PCD_PAY_CARDNUM ?? '0000-****-****-0000';
							break;
					}

					HotopayModel::insertBillingKey($billing_key_obj);
				}
			}
			else
			{
				// 카드/계좌 해지
				$key_hash = strtoupper(hash('sha256', $this->user->member_srl . $payer_id));
				HotopayModel::deleteBillingKeyByKeyHash($member_srl, $key_hash);
			}
		}

		return new BaseObject();
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

		$purchase = HotopayModel::getPurchase($purchase_srl);

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
		$this->clearPurchasedCartItem($purchase);

		$products = HotopayModel::getProductsByPurchaseSrl($purchase_srl);

		$group_srls = array();
		$oMemberController = MemberController::getInstance();
		foreach($products as $product)
		{
			$group_srl = $product->product_buyer_group;
			if($group_srl != 0)
			{
				$group_srls[] = $group_srl;
				$oMemberController->addMemberToGroup($member_srl, $group_srl);
			}
		}

		$config = $this->getConfig();
		if ($config->change_group_to_regular_when_pay == 'Y')
		{
			if ($config->regular_group_srl != 0)
			{
				$oMemberController->addMemberToGroup($member_srl, $config->regular_group_srl);
			}

			if ($config->associate_group_srl != 0)
			{
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->group_srl = $config->associate_group_srl;
				$output = executeQuery('member.deleteMemberGroupMember', $args); // 그룹제거
			}
		}

		$validator = new HotopayLicenseValidator();
		$isLicenseValid = $validator->validate($config->hotopay_license_key);
		if ($isLicenseValid)
		{
			$reward_point = $purchase->reward_point;

			if ($reward_point > 0)
			{
				$oPointController = \PointController::getInstance();
				Context::set('__point_message__', sprintf('포인트 적립 #%d', $purchase_srl));
				$output = $oPointController->setPoint($member_srl, $reward_point, 'add');
			}
		}

		$trigger_obj->group_srls = $group_srls;
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
		$purchase = HotopayModel::getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;
		if(empty($member_srl))
			return $this->createObject(-1, "member_srl을 찾을 수 없습니다.");

		$original_pay_status = $purchase->pay_status;
		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$args->pay_status = 'REFUNDING';
		executeQuery('hotopay.updatePurchaseStatus', $args);

		switch($purchase->pay_method)
		{
			case 'card':
			case 'voucher':
			case 'cellphone':
			case 'toss':
				if ($purchase->is_billing == 'Y')
				{
					$tossController = new Toss(true);
				}
				else
				{
					$tossController = new Toss();
				}

				$output = $tossController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount);
				break;

			case 'v_account':
				if ($purchase->is_billing == 'Y')
				{
					$tossController = new Toss(true);
				}
				else
				{
					$tossController = new Toss();
				}

				$output = $tossController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount, $bank_info);
				break;

			case 'paypal':
				$paypalController = new Paypal();
				$output = $paypalController->cancelOrder($purchase_srl, $cancel_reason, HotopayModel::changeCurrency('KRW', 'USD', $cancel_amount));
				break;

			case 'kakaopay':
				$kakaoPayController = new KakaoPay();
				$output = $kakaoPayController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount);
				break;

			case 'paypl_transfer':
			case 'paypl_card':
				$paypleController = new Payple();
				$output = $paypleController->cancelOrder($purchase_srl, $cancel_reason, $cancel_amount);
				break;

			case 'n_account':
				$output = $this->createObject();
				break;

			case 'point':
				$oPointController = \PointController::getInstance();
				Context::set('__point_message__', sprintf('구매 환불 #%d (사유: %s)', $purchase_srl, $cancel_reason));
				$output = $oPointController->setPoint($member_srl, $cancel_amount, 'add');
				break;

			default:
				$output = $this->createObject(-1, sprintf("환불할 수 없는 결제수단입니다. (%s)", $purchase->pay_method));
				break;
		}

		if($output->error == 0)
		{
			return $this->_RefundProcess($purchase_srl, $output->data);
		}
		else
		{
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = $original_pay_status;
			executeQuery('hotopay.updatePurchaseStatus', $args);

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
		$purchase = HotopayModel::getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;

		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$args->pay_status = 'REFUNDED';
		$args->pay_data = json_encode($output_data);
		executeQuery('hotopay.updatePurchaseStatus', $args);
		executeQuery('hotopay.updatePurchaseData', $args);

		$products = HotopayModel::getProductsByPurchaseSrl($purchase_srl);

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

		HotopayModel::rollbackOptionStock($purchase_srl);
		$this->refundUsedPoint($purchase_srl);

		$items = HotopayModel::getPurchaseItems($purchase_srl);
		foreach($items as $item)
		{
			if ($item->subscription_srl > 0)
			{
				$args = new stdClass();
				$args->subscription_srl = $item->subscription_srl;
				$args->status = 'CANCELED_REFUND';
				HotopayModel::updateSubscription($args);
			}
		}

		$reward_point = $purchase->reward_point;

		if ($reward_point > 0)
		{
			$oPointController = \PointController::getInstance();
			Context::set('__point_message__', sprintf('포인트 회수 #%d', $purchase_srl));
			$oPointController->setPoint($member_srl, $reward_point, 'minus');
		}

		$oMemberController = MemberController::getInstance();
		if(version_compare(__XE_VERSION__, '2.0.0', '<'))
		{
			$oMemberController->_clearMemberCache($member_srl); // for old rhymix
		}
		else
		{
			$oMemberController->clearMemberCache($member_srl);
		}

		$trigger_obj = new stdClass();
		$trigger_obj->member_srl = $member_srl;
		$trigger_obj->purchase_srl = $purchase_srl;
		ModuleHandler::triggerCall('hotopay.refundPurchase', 'after', $trigger_obj);

		$this->_MessageMailer("REFUNDED", $purchase);
		$this->_AdminMailer("REFUNDED", $purchase);

		return $this->createObject();
	}

	/**
	 * 구매시 사용한 포인트를 환불해주는 함수입니다.
	 *
	 * @param int $purchase_srl 결제 번호입니다.
	 * @return void
	 */
	public function refundUsedPoint(int $purchase_srl)
	{
		$purchase = HotopayModel::getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;
		$used_point = $purchase->used_point;

		$oPointController = \PointController::getInstance();
		Context::set('__point_message__', sprintf('상품 구매시 사용한 포인트 환불 #%d', $purchase_srl));
		$oPointController->setPoint($member_srl, $used_point, 'plus');
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
		$oHotopayModel = HotopayModel::getInstance();
		$oCommController = \CommunicationController::getInstance();

		switch($status)
		{
			case 'DONE':
				if(in_array(1, $config->purchase_success_notification_method))
				{
					// 쪽지 알림
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
		if ($config->admin_mailing !== 'Y')
		{
			return;
		}

		if (!in_array($status, $config->admin_mailing_status))
		{
			return;
		}

		if ($purchase->is_billing == 'Y' && $status == 'DONE' && !in_array('BILLING_DONE', $config->admin_mailing_status))
		{
			return;
		}

		$member_srl = $purchase->member_srl;
		$member_info = \MemberModel::getMemberInfoByMemberSrl($member_srl);
		$price = number_format($purchase->product_purchase_price);
		$purchase_date = date("Y-m-d H:i:s", $purchase->regdate);
		$pay_method_korean = HotopayModel::purchaseMethodToString($purchase->pay_method);
		$purchase_title_substr = mb_substr($purchase->title, 0, 18);

		switch($status)
		{
			case "DONE":
				$message_body = "결제 완료 알림 메일입니다.<br><br>결제 코드: HT{$purchase->purchase_srl}<br>회원 닉네임: {$member_info->nick_name}<br>회원 이름: {$member_info->user_name}<br>결제 품목: {$purchase->title}<br>결제 금액: {$price}<br>결제 수단: {$pay_method_korean}<br>결제 시각: {$purchase_date}<br>";
				$this->_sendMail(4, "[HotoPay] 회원의 결제가 완료되었습니다.", $message_body);

				$sms_body = "[Hotopay] 결제알림 ({$pay_method_korean}/{$price}) {$purchase_title_substr}";
				$this->_sendSMS(4, $sms_body);
				break;

			case 'REFUNDED':
				$message_body = "결제 환불 알림 메일입니다.<br><br>결제 코드: HT{$purchase->purchase_srl}<br>회원 닉네임: {$member_info->nick_name}<br>회원 이름: {$member_info->user_name}<br>결제 품목: {$purchase->title}<br>결제 금액: {$price}<br>결제 수단: {$pay_method_korean}<br>결제 시각: {$purchase_date}<br>";
				$this->_sendMail(4, "[HotoPay] 회원의 결제가 환불되었습니다.", $message_body);

				$sms_body = "[Hotopay] 환불알림 ({$pay_method_korean}/{$price}) {$purchase_title_substr}";
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
	 * @return boolean
	 */
	public function _sendMail($member_srl, $mail_title, $mail_content)
	{
		$member_info = \MemberModel::getMemberInfoByMemberSrl($member_srl);
		if (empty($member_info->email_address))
		{
			return false;
		}

		$email_address = $member_info->email_address;
		$nick_name = $member_info->nick_name ?? '구매자';

		$oMail = new \Rhymix\Framework\Mail();
		$oMail->setSubject($mail_title);
		$oMail->setBody($mail_content);
		$oMail->addTo($email_address, $nick_name);
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
		$member_info = \MemberModel::getMemberInfoByMemberSrl($member_srl);

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

	public function clearPurchasedCartItem($purchase): object
	{
		$items = HotopayModel::getPurchaseItems($purchase->purchase_srl);
		$member_srl = $purchase->member_srl;

		$oDB = DB::getInstance();
		$oDB->begin();
		foreach ($items as $item)
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$args->product_srl = $item->product_srl;
			$args->option_srl = $item->option_srl;
			$args->quantity = $item->quantity;
			$output = executeQuery('hotopay.deleteCartItemWithData', $args);
			if(!$output->toBool())
			{
				$oDB->rollback();
				throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
			}
		}

		$oDB->commit();
		$this->deleteCache('cart_item_count_' . $member_srl);
		return new BaseObject();
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
			$oMemberController = MemberController::getInstance();
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

		$module_info = ModuleModel::getModuleInfoByMid($mid);
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

			$oHotopayAdminController = HotopayAdminController::getInstance();
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

		$module_info = ModuleModel::getModuleInfoByMid($mid);
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
			$oHotopayAdminController = HotopayAdminController::getInstance();
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
		$current_cart_item_count = HotopayModel::getCartItemCount($member_srl);
		if($current_cart_item_count >= $config->cart_item_limit)
		{
			return new BaseObject(-1, '장바구니에는 최대 ' . $config->cart_item_limit . '개의 상품만 담을 수 있습니다.');
		}

		$product_srl = Context::get('product_srl');
		$option_srl = Context::get('option_srl');
		$quantity = Context::get('quantity');

		if (!$product_srl || !$option_srl || !$quantity)
		{
			return new BaseObject(-1, '필수 정보가 없습니다.');
		}

		$product_info = HotopayModel::getProduct($product_srl);
		if(!$product_info)
		{
			return new BaseObject(-1, '상품 정보가 없습니다.');
		}

		$option_info = HotopayModel::getOption($option_srl);
		if(!$option_info)
		{
			return new BaseObject(-1, '옵션 정보가 없습니다.');
		}

		if ($option_info->product_srl != $product_srl)
		{
			return new BaseObject(-1, '상품과 옵션이 일치하지 않습니다.');
		}

		$cart_items = HotopayModel::getCartItems($member_srl);
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

		HotopayModel::insertCartItem($args);
		$this->deleteCache('cart_item_count_' . $member_srl);

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

		if (!$cart_item_srl)
		{
			return new BaseObject(-1, '필수 정보가 없습니다.');
		}

		HotopayModel::deleteCartItem($cart_item_srl, $member_srl);
		$this->deleteCache('cart_item_count_' . $member_srl);

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

		if (!$cart_item_srl || !$option_srl || !$quantity)
		{
			return new BaseObject(-1, '필수 정보가 없습니다.');
		}

		$args = new stdClass();
		$args->cart_item_srl = $cart_item_srl;
		$args->member_srl = $member_srl;
		$args->option_srl = $option_srl;
		$args->quantity = $quantity;
		$args->regdate = date('YmdHis');

		HotopayModel::updateCartItem($args);

		$this->setMessage('장바구니가 수정되었습니다.');
	}
}
