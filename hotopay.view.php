<?php

/**
 * Hoto Pay
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayView extends Hotopay
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 스킨 경로 지정
		$this->setTemplatePath($this->module_path . 'skins/' . ($this->module_info->skin ?: 'default'));
	}
	
	/**
	 * 메인 화면 예제
	 */
	public function dispHotopayIndex()
	{
		// 스킨 파일명 지정
		$this->setTemplateFile('index');
	}

	public function dispHotopayOrderPage()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		Context::set('hotopay_config', $config);
		Context::set('vars', $vars);

		Context::set('toss_enabled', $config->toss_enabled == 'Y' && !empty($config->toss_payments_client_key) && !empty($config->toss_payments_secret_key));
		Context::set('paypal_enabled', $config->paypal_enabled == 'Y' && !empty($config->paypal_client_key) && !empty($config->paypal_secret_key));
		Context::set('n_account_enabled', $config->n_account_enabled == 'Y' && !empty($config->n_account_string));

		$args = new stdClass();
		$args->product_srl = array($vars->product_id);
		$product_list = executeQueryArray("hotopay.getProducts", $args);

		if((!$product_list->toBool()) || (count($product_list->data) < 1))
		{
			return $this->createObject(-1, "물품 데이터가 없습니다.");
		}

		Context::set('product_list', $product_list->data);

		$this->setTemplateFile('order_page');
	}

	public function dispHotopayPayProcess()
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
		}


		Context::set('purchase', $purchase_data);

		$this->setTemplateFile('pay_process');
	}

	public function dispHotopayOrderResult()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		Context::set('hotopay_config', $config);
		Context::set('vars', $vars);

		$pay_data = $_SESSION['hotopay_'.$vars->order_id];
		if(empty($pay_data)) return $this->createObject(-1, "결제 데이터가 없습니다.");

		Context::set('pay_data', (object)$pay_data);

		if(strcmp($pay_data->method, "n_account") === 0)
		{
			$n_account_html = nl2br($config->n_account_string);
			Context::set('n_account_html', $n_account_html);
		}

		if($pay_data->p_status == "success")
			$this->setTemplateFile('order_success');
		else
			$this->setTemplateFile('order_failed');
	}

	public function dispHotopayOrderList()
	{
		$config = $this->getConfig();
		Context::set('hotopay_config', $config);

		$logged_info = Context::get('logged_info');
		Context::set('logged_info', $logged_info);

		if(empty($logged_info->member_srl))
		{
			return $this->createObject(-1, "로그인이 필요합니다.");
		}

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$output = executeQueryArray('hotopay.getPurchases', $args);

		if(!$output->toBool())
		{
			return $this->createObject(-1, "Query Error (code: 1001)");
		}

		Context::set('purchase_list', array_reverse($output->data));

		$this->setTemplateFile('order_list');
	}
}
