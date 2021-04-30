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

		executeQuery("hotopay.insertPurchase", $args);

		$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayPayToss','order_id',$order_id));
	}

	public function procHotopayPayStatus()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		if(strcmp($vars->pay_status, "success") === 0) // 결제 성공
		{
			if(strcmp($vars->order_id, $vars->orderId) !== 0)
			{
				return $this->createObject(-1, "결제 실패. (code: 1003)");
			}

			$purchase_srl = substr($vars->orderId, 2);

			$args = new stdClass();
			$args->purchase_srl = $purchase_srl;
			$purchase = executeQuery('hotopay.getPurchase', $args);
			if(!$purchase->toBool())
			{
				return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
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

			$response_json->p_status = "success";
			$_SESSION['hotopay_'.$vars->orderId] = $response_json;
			$this->setRedirectUrl(getUrl('','mid','hotopay','act','dispHotopayOrderResult','order_id',$vars->orderId));
			return;
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

	public function procHotopayPaymentCallback()
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

		$oCommController = getController('communication');
		$oCommController->sendMessage($member_srl, 4, '물품 결제가 완료되었습니다.', "'{$purchase_data->t}' 물품이 성공적으로 결제되었습니다.<br><br>파일은 상단바에 [스토어] > [다운로드]에서 받으실 수 있습니다.<br><br><a href=\"".getUrl("","mid","hotopay","act","dispHotopayOrderList")."\">[결제 확인하기]</a>");

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

	public function _CancelPurchase($purchase_srl, $member_srl = -1)
	{
		
	}
}
