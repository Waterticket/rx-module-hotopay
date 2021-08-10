<?php

/**
 * Hoto Pay
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayController extends Hotopay
{
	public function procHotopayOrderProcess()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');
		if($logged_info == null) return $this->createObject(-1, "로그인이 필요합니다");
		// if($logged_info->member_srl != 4) return $this->createObject(-1, "현재 결제 기능 점검중입니다. 잠시 뒤에 다시 시도 해주세요.");

		$order_id = getNextSequence();

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $logged_info->member_srl;

		$cond = new stdClass();
		$cond->product_srl = $vars->buy_product;
		$product_list = executeQueryArray("hotopay.getProducts", $cond);

		$title = "";
		$tc = -1;
		$total_price = 0;
		$original_price = 0;

		foreach($product_list->data as $product)
		{
			if($tc < 0)
			{
				$title = $product->product_name;
				$tc++;
			}
			$opt_num = $vars->opt[$product->product_srl]; // 숫자 [0번째, 1번째, ..]
			$p_opt = preg_split("/\r\n|\n|\r/", $product->product_option);
			$f_opt = array();
			foreach($p_opt as $_opt)
			{
				$_opt = mb_substr($_opt, 1, -1);
				array_push($f_opt, explode('/' , $_opt));
			}

			$total_price += $product->product_sale_price + intval($f_opt[$opt_num][1]);
			$original_price += $product->product_original_price;
		}

		if($tc > 0)
		{
			$title .= " 외 ".$tc."개";
		}

		$args->products = json_encode(array("t"=>$title, "bp"=>$vars->buy_product, "opt"=>$vars->opt));
		$args->pay_method = $vars->pay_method;
		$args->product_purchase_price = $total_price;
		$args->product_original_price = $original_price;
		$args->pay_status = "PENDING";
		$args->regdate = time();
		$args->pay_data = '';

		switch($vars->pay_method)
		{
			case 'paypal':
				$usd_total = round($total_price/1000, 2);

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

			case "n_account":
				$order_obj = new stdClass();
				$order_obj->depositor_name = mb_substr(($logged_info->user_name ?: ('구매자'.rand(100, 999))), 0, 6);
				$args->pay_data = json_encode($order_obj);
				break;
		}

		executeQuery("hotopay.insertPurchase", $args);

		$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayPayProcess','order_id',$order_id));
	}

	public function procHotopayPayStatus()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		if(strcmp($vars->pay_status, "success") === 0) // 결제 성공
		{
			$purchase_srl = substr($vars->order_id, 2);

			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$purchase = executeQuery('hotopay.getPurchase', $args);
			if(!$purchase->toBool())
			{
				return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
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

				$url = "https://api.tosspayments.com/v1/payments/{$vars->paymentKey}";
				$headers = array(
					'Content-Type: application/json',
					'Authorization: Basic '. base64_encode("$config->toss_payments_secret_key:")
				);
				$post_field_string = json_encode(array(
					"orderId" => $vars->orderId,
					"amount" => $purchase->data->product_purchase_price
				));

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
				curl_setopt($ch, CURLOPT_POST, true);
				$response = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);

				$response_json = json_decode($response);

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
					$args->purchase_srl = substr($vars->orderId, 2);
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
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
				$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
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
					$paypalController->captureOrder($pay_data->id);

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
					$args->purchase_srl = substr($vars->orderId, 2);
					$args->pay_status = "FAILED";
					executeQuery('hotopay.updatePurchaseStatus', $args);

					$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
					return;
				}

				$order_detail->orderId = $vars->order_id;
				$order_detail->p_status = "success";
				$order_detail->method = "paypal";
				$order_detail->product_title = $purchase_data->t;
				$_SESSION['hotopay_'.$vars->orderId] = $order_detail;
				$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
				return;
			}
			else if(strcmp($vars->pay_pg, "n_account") === 0) // 무통장 처리
			{
				$args->pay_status = 'WAITING_FOR_DEPOSIT';
				executeQuery('hotopay.updatePurchaseStatus', $args);
				$pay_data = json_decode($purchase->data->pay_data);

				$order_detail = new stdClass();
				$order_detail->orderId = $vars->order_id;
				$order_detail->p_status = "success";
				$order_detail->method = "n_account";
				$order_detail->totalAmount = $purchase->data->product_purchase_price;
				$order_detail->depositor_name = $pay_data->depositor_name;
				$order_detail->product_title = $purchase_data->t;

				$_SESSION['hotopay_'.$vars->order_id] = $order_detail;
				$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->order_id));
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
			$args->purchase_srl = substr($order_id, 2);
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

			executeQuery('hotopay.updatePurchaseStatus', $args);
			
			$_SESSION['hotopay_'.$vars->orderId] = (object) $res_array;

			$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
		}
	}

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
		$purchase_srl = substr($vars->orderId, 2);

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

	public function _ActivePurchase($purchase_srl, $member_srl = -1)
	{
		$args = new stdClass();
		$args->purchase_srl = $purchase_srl;
		$purchase = executeQuery('hotopay.getPurchase', $args);

		$args->pay_status = "DONE";
		executeQuery('hotopay.updatePurchaseStatus', $args);

		if(!$purchase->toBool())
		{
			return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
		}

		$purchase_data = json_decode($purchase->data->products);

		$logged_info = Context::get('logged_info');
		if($member_srl == -1) $member_srl = $logged_info->member_srl;

		$this->_MessageMailer("DONE", $purchase->data);
		$this->_AdminMailer("DONE", $purchase->data);

		$args = new stdClass();
		$args->product_srl = $purchase_data->bp;
		$products = executeQueryArray('hotopay.getProducts', $args);
		if(!$products->toBool())
		{
			return $this->createObject(-1, "물품이 존재하지 않습니다.");
		}

		$oMemberController = getController('member');
		foreach($products->data as $product)
		{
			$group_srl = $product->product_buyer_group;
			if($group_srl != 0)
			{
				$oMemberController->addMemberToGroup($member_srl, $group_srl);
			}
		}
	}

	public function _CancelPurchase($purchase_srl, $cancel_reason = 'Hotopay Refund', $cancel_amount = -1, $bank_info = array())
	{
		$oHotopayModel = getModel('hotopay');
		$purchase = $oHotopayModel->getPurchase($purchase_srl);
		$member_srl = $purchase->member_srl;

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
				break;
		}

		debugPrint($output);

		if($output->error == 0)
		{
			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$args->pay_status = 'CANCELED';
			$args->pay_data = json_encode($output->data);
			executeQuery('hotopay.updatePurchaseStatus', $args);
			executeQuery('hotopay.updatePurchaseData', $args);

			$purchase_data = json_decode($purchase->products);
			$products = $oHotopayModel->getProducts($purchase_data->bp);

			$oMemberController = getController('member');
			foreach($products as $product)
			{
				$group_srl = $product->product_buyer_group;
				if($group_srl != 0)
				{
					// @todo 그룹 제거하기
					// $member_srl
				}
			}

			return $this->createObject();
		}
		else
		{
			return $output;
		}
	}

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
		}
	}

	public function _AdminMailer($status, $purchase_data)
	{
		$member_srl = $purchase_data->member_srl;
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		$pay_data = json_decode($purchase_data->pay_data);
		$price = number_format($purchase_data->product_purchase_price);
		$purchase_date = date("Y-m-d H:i:s", $purchase_data->regdate);

		switch($status)
		{
			case "DONE":
				$message_body = "결제 완료 알림 메일입니다.<br><br>결제 코드: HT{$purchase_data->purchase_srl}<br>회원 닉네임: {$member_info->nick_name}<br>회원 이름: {$member_info->user_name}<br>결제 품목: {$pay_data->t}<br>결제 금액: {$price}<br>결제시각: {$purchase_date}<br>";
				$this->_sendMail(4, "[HotoPay] 회원의 결제가 완료되었습니다.", $message_body);
				break;
		}
	}

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
}
