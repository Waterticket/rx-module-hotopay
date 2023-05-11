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

	public function dispHotopayCartCheckout()
	{
		$logged_info = Context::get('logged_info');	
		$member_srl = $logged_info->member_srl;
		if (!$member_srl)
		{
			throw new \Rhymix\Framework\Exception('로그인이 필요합니다.');
		}

		$oHotopayModel = getModel('hotopay');
		$cart_items = $oHotopayModel->getCartItems($member_srl);
		Context::set('cart_items', $cart_items);

		if (!$cart_items)
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', 'hotopay', 'act', 'dispHotopayCart'));
			return;
		}

		$this->dispHotopayOrderPage();

		$purchase_price = 0;
		foreach ($cart_items as $item)
		{
			$purchase_price += $item->option_price * $item->quantity;
		}
		Context::set('purchase_price', $purchase_price);

		$billing_keys = $oHotopayModel->getBillingKeys($member_srl);
		Context::set('billing_keys', $billing_keys);

		// 스킨 파일명 지정
		$this->setTemplateFile('cart_checkout');
	}

	public function dispHotopayOrderPage()
	{
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		Context::set('hotopay_config', $config);
		Context::set('vars', $vars);

		$iamport_enabled = $config->iamport_enabled == 'Y' && !empty($config->iamport_mid) && !empty($config->iamport_rest_api_key) && !empty($config->iamport_rest_api_secret);
		$payple_enabled = $config->payple_enabled == 'Y' && !empty($config->payple_cst_id) && !empty($config->payple_cust_key);

		Context::set('toss_enabled', $config->toss_enabled == 'Y' && !empty($config->toss_payments_client_key) && !empty($config->toss_payments_secret_key));
		Context::set('paypal_enabled', $config->paypal_enabled == 'Y' && !empty($config->paypal_client_key) && !empty($config->paypal_secret_key));
		Context::set('kakaopay_enabled', $config->kakaopay_enabled == 'Y' && !empty($config->kakaopay_admin_key) && !empty($config->kakaopay_cid_key));
		Context::set('inicis_enabled', $config->inicis_enabled == 'Y' && $iamport_enabled);
		Context::set('payple_enabled', $config->payple_enabled == 'Y' && $payple_enabled);
		Context::set('n_account_enabled', $config->n_account_enabled == 'Y' && !empty($config->n_account_string));

		$oHotopayModel = getModel('hotopay');
		$product_list = $oHotopayModel->getProducts($vars->product_id);
		Context::set('product_list', $product_list);

		$is_non_billing_product_exist = false;
		$is_billing_product_exist = false;
		foreach ($product_list as $product)
		{
			if ($product->is_billing == 'Y')
			{
				$is_billing_product_exist = true;
			}
			else
			{
				$is_non_billing_product_exist = true;
			}
		}

		if ($is_billing_product_exist && $is_non_billing_product_exist)
		{
			throw new \Rhymix\Framework\Exception('정기결제 상품과 일반결제 상품을 동시에 구매할 수 없습니다.');
		}

		if ($is_billing_product_exist)
		{
			$billing_keys = $oHotopayModel->getBillingKeys($this->user->member_srl);
			Context::set('billing_keys', $billing_keys);
			Context::set('purchase_type', 'billing');
		}
		else
		{
			Context::set('purchase_type', 'normal');
		}

		$logged_info = Context::get('logged_info');
		$oPointModel = getModel('point');
		$point = $oPointModel->getPoint($logged_info->member_srl, true);
		Context::set('point', $point);

		$this->setTemplateFile('order_page');
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

	public function dispHotopayCart()
	{
		$config = $this->getConfig();
		Context::set('hotopay_config', $config);

		$logged_info = Context::get('logged_info');
		Context::set('logged_info', $logged_info);

		$member_srl = $logged_info->member_srl;

		if(empty($member_srl))
		{
			return $this->createObject(-1, "로그인이 필요합니다.");
		}

		$oHotopayModel = getModel('hotopay');
		$cart_items = $oHotopayModel->getCartItems($member_srl);
		Context::set('cart_items', $cart_items);

		$this->setTemplateFile('cart');
	}
}
