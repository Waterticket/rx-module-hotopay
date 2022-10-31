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
		$logged_info = Context::get('logged_info');
		if($logged_info->member_srl != 4)
		{
			throw new \Rhymix\Framework\Exception('잘못된 접근입니다.');
		}

		// 스킨 파일명 지정
		$this->setTemplateFile('index');
	}

	public function dispHotopayOrderPage()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		Context::set('hotopay_config', $config);
		Context::set('vars', $vars);

		$iamport_enabled = $config->iamport_enabled == 'Y' && !empty($config->iamport_mid) && !empty($config->iamport_rest_api_key) && !empty($config->iamport_rest_api_secret);

		Context::set('toss_enabled', $config->toss_enabled == 'Y' && !empty($config->toss_payments_client_key) && !empty($config->toss_payments_secret_key));
		Context::set('paypal_enabled', $config->paypal_enabled == 'Y' && !empty($config->paypal_client_key) && !empty($config->paypal_secret_key));
		Context::set('kakaopay_enabled', $config->kakaopay_enabled == 'Y' && !empty($config->kakaopay_admin_key) && !empty($config->kakaopay_cid_key));
		Context::set('inicis_enabled', $config->inicis_enabled == 'Y' && $iamport_enabled);
		Context::set('n_account_enabled', $config->n_account_enabled == 'Y' && !empty($config->n_account_string));

		$oHotopayModel = getModel('hotopay');
		$product_list = $oHotopayModel->getProducts($vars->product_id);

		Context::set('product_list', $product_list);

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

			case 'inicis':
				$purchase_data->pay_method_korean = '이니시스';
				$purchase_data->pay_pg = 'inicis';
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

		if($_SESSION['__hotopay_purchase_success_after_url__'])
		{
			Context::set('purchase_success_after_url', $_SESSION['__hotopay_purchase_success_after_url__']);
			unset($_SESSION['__hotopay_purchase_success_after_url__']);
		}
		else
		{
			$purchase_success_after_url = getUrl("","mid","hotopay","act","dispHotopayOrderList");
			Context::set('purchase_success_after_url', $purchase_success_after_url);
		}

		if($_SESSION['__hotopay_purchase_failed_after_url__'])
		{
			Context::set('purchase_failed_after_url', $_SESSION['__hotopay_purchase_failed_after_url__']);
			unset($_SESSION['__hotopay_purchase_failed_after_url__']);
		}
		else
		{
			$purchase_failed_after_url = getUrl("","mid","hotopay","act","dispHotopayOrderList");
			Context::set('purchase_failed_after_url', $purchase_failed_after_url);
		}

		if($pay_data->p_status == "success")
			$this->setTemplateFile('order_success');
		else
			$this->setTemplateFile('order_failed');
	}

	public function dispHotopayOrderList()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		Context::set('hotopay_config', $config);

		$logged_info = Context::get('logged_info');
		if($vars->target_member_srl && $logged_info->is_admin == 'Y')
		{
			$member_srl = $vars->target_member_srl;
			$logged_info = MemberModel::getMemberInfoByMemberSrl($member_srl);
			
			if(!$logged_info->member_srl) return $this->createObject(-1, "존재하지 않는 회원입니다.");
		}

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

		$purchase_list = array_reverse($output->data);


		$obj = new stdClass();
		$obj->purchase_list = &$purchase_list;
		ModuleHandler::triggerCall('hotopay.displayOrderList', 'before', $obj);

		Context::set('purchase_list', $purchase_list);

		$this->setTemplateFile('order_list');
	}
}
