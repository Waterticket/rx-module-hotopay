<?php

/**
 * Hoto Pay
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayAdminView extends Hotopay
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 관리자 화면 템플릿 경로 지정
		$this->setTemplatePath($this->module_path . 'tpl');
		
		$update_needed = $_COOKIE['ht_update_check'];
		if(!isset($_COOKIE['ht_update_check']))
		{
			$update_needed = $this->githubUpdateCheck();
			setcookie('ht_update_check', (int)$update_needed, time() + 21600);
		}

		Context::set('update_needed', (int)$update_needed);
	}

	public function dispHotopayAdminDashBoard()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$oHotopayAdminModel = getAdminModel('hotopay');
		$total = $oHotopayAdminModel->getSales(0);
		$month = $oHotopayAdminModel->getSales(strtotime("first day of this month midnight"));
		$last_month = $oHotopayAdminModel->getSales(strtotime("first day of last month midnight"), strtotime("first day of this month midnight"));
		$week = $oHotopayAdminModel->getSales(strtotime('monday this week midnight'));
		$last_week = $oHotopayAdminModel->getSales(strtotime('monday this week midnight') - 604800, strtotime('monday this week midnight'));
		$today = $oHotopayAdminModel->getSales(strtotime("today midnight"));
		$yesterday = $oHotopayAdminModel->getSales(strtotime("today midnight") - 86400, strtotime("today midnight"));

		Context::set('total', $total);
		Context::set('month', $month);
		Context::set('last_month', $last_month);
		Context::set('week', $week);
		Context::set('last_week', $last_week);
		Context::set('today', $today);
		Context::set('yesterday', $yesterday);
		
		// 스킨 파일 지정
		Context::setBrowserTitle('대시보드 - Hotopay');
		$this->setTemplateFile('dashboard');
	}
	
	public function dispHotopayAdminConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$oMemberModel = MemberModel::getInstance();
		$groups = $oMemberModel->getGroups();
		Context::set('groups', $groups);
		
		// 스킨 파일 지정
		Context::setBrowserTitle('모듈 설정 - Hotopay');
		$this->setTemplateFile('config');
	}

	public function dispHotopayAdminPaymentGatewayConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);
		
		// 스킨 파일 지정
		Context::setBrowserTitle('PG 설정 - Hotopay');
		$this->setTemplateFile('pg_config');
	}

	public function dispHotopayAdminProductList()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1; ///< 페이지
		$args->list_count = 20; ///< 한페이지에 보여줄 기록 수
		$args->page_count = 10; ///< 페이지 네비게이션에 나타날 페이지의 수
		$args->order_type = 'desc';
		$output = executeQueryArray('hotopay.getProductsPage', $args);

		if(!$output->toBool())
		{
			return $this->createObject(-1, "DB Error: ".$output->message);
		}

		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('products', $output->data); // 상품 데이터 추가
		
		// 스킨 파일 지정
		Context::setBrowserTitle('상품 목록 - Hotopay');
		$this->setTemplateFile('product_list');
	}

	public function dispHotopayAdminInsertProduct()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$oMemberModel = getModel('member');
		$groups = $oMemberModel->getGroups();
		
		Context::set('groups', $groups);

		// 스킨 파일 지정
		Context::setBrowserTitle('상품 추가 - Hotopay');
		$this->setTemplateFile('insert_product');
	}

	public function dispHotopayAdminInsertPurchase()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$oHotopayModel = getModel('hotopay');
		$products = $oHotopayModel->getProductsAll();
		Context::set('products', $products);

		// 스킨 파일 지정
		Context::setBrowserTitle('결제 데이터 추가 - Hotopay');
		$this->setTemplateFile('insert_purchase');
	}

	public function dispHotopayAdminPurchaseList()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		HotopayModel::updateExpiredPurchaseStatus();

		$sort_index = $vars->sort_index ?: 'purchase_srl';
		$sort_order = in_array($vars->sort_order, ['asc','desc']) ? $vars->sort_order : 'desc';

		$search_target = $vars->search_target;
		$search_keyword = $vars->search_keyword;
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1; ///< 페이지
		$args->list_count = 20; ///< 한페이지에 보여줄 기록 수
		$args->page_count = 10; ///< 페이지 네비게이션에 나타날 페이지의 수
		$args->sort_index = $sort_index;
		$args->order_type = $sort_order;

		// search condition
		if(!empty($search_target) && !empty($search_keyword))
		{
			switch($search_target)
			{
				case 'purchase_srl':
				case 'member_srl':
					$args->{$search_target} = preg_replace('/[^0-9]/', '', $search_keyword);
					break;

				case 'user_id':
				case 'nick_name':
				case 'user_name':
				case 'phone_number':
				case 'email_address':
					$search_arg = new stdClass();
					$search_arg->{$search_target} = $search_keyword;
					$search_result = executeQueryArray('hotopay.getMemberSrlBySearch', $search_arg);
					$member_srls = array_map(function($v) { return $v->member_srl; }, $search_result->data);
					$args->member_srl = $member_srls;
					break;
			}
		}

		$output = executeQueryArray('hotopay.getPurchasesPage', $args);

		if(!$output->toBool())
		{
			return $this->createObject(-1, "DB Error: ".$output->message);
		}

		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('purchase_list', $output->data);

		// 스킨 파일 지정
		Context::setBrowserTitle('결제 목록 - Hotopay');
		$this->setTemplateFile('purchase_list');
	}

	public function dispHotopayAdminPurchaseData()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		$vars = Context::getRequestVars();

		// Context에 세팅
		Context::set('hotopay_config', $config);

		$args = new stdClass();
		$args->purchase_srl = $vars->purchase_srl;
		
		$output = executeQuery('hotopay.getPurchase', $args);

		if(!$output->toBool())
		{
			return $this->createObject(-1, "DB Error: ".$output->message);
		}

		Context::set('purchase_data', $output->data);

		// 스킨 파일 지정
		Context::setBrowserTitle('결제 데이터 - Hotopay');
		$this->setTemplateFile('purchase_data');
	}

	public function dispHotopayAdminModifyProduct()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		$product_srl = $vars->product_srl;
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		$oMemberModel = getModel('member');
		$groups = $oMemberModel->getGroups();
		
		Context::set('groups', $groups);

		$oHotopayModel = getModel('hotopay');
		$product = $oHotopayModel->getProduct($product_srl);
		$product->product_option = $oHotopayModel->getProductOptions($product_srl);
		Context::set('product', $product);

		// 스킨 파일 지정
		Context::setBrowserTitle('상품 수정 - Hotopay');
		$this->setTemplateFile('modify_product');
	}

	public function dispHotopayAdminNotification()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);

		// 스킨 파일 지정
		Context::setBrowserTitle('알림 설정 - Hotopay');
		$this->setTemplateFile('notify_config');
	}

	public function dispHotopayAdminPurchaseExtraInfo()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		// Context에 세팅
		Context::set('hotopay_config', $config);

		// 스킨 파일 지정
		Context::setBrowserTitle('추가정보 설정 - Hotopay');
		$this->setTemplateFile('purchase_extra_info_config');
	}

    public function dispHotopayAdminSubscriptionIndex() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);

        $vars = Context::getRequestVars();
        $args = new \stdClass();
        $args->page = $vars->page ? $vars->page : 1;
        $args->search_target = $vars->search_target ? $vars->search_target : '';
        $args->search_keyword = $vars->search_keyword ? $vars->search_keyword : '';

        $output = HotopayModel::getSubscriptionList($args);
        Context::set('subscription_list', $output->data);
        Context::set('total_count', $output->total_count);
        Context::set('total_page', $output->total_page);
        Context::set('page', $output->page);
        Context::set('page_navigation', $output->page_navigation);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('정기결제 목록 - Hotopay');
        $this->setTemplateFile('index_subscription');
    }

    public function dispHotopayAdminInsertSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('정기결제 추가 - Hotopay');
        $this->setTemplateFile('insert_subscription');
    }

    public function dispHotopayAdminUpdateSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
        $output = HotopayModel::getSubscription($vars->subscription_srl);
        Context::set('subscription', $output);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('정기결제 수정 - Hotopay');
        $this->setTemplateFile('update_subscription');
    }

    public function dispHotopayAdminDeleteSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
        $output = HotopayModel::getSubscription($vars->subscription_srl);
        Context::set('subscription', $output);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('정기결제 삭제 - Hotopay');
        $this->setTemplateFile('delete_subscription');
    }

	public function dispHotopayAdminBillingKeyIndex() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);

        $vars = Context::getRequestVars();
        $args = new \stdClass();
        $args->page = $vars->page ? $vars->page : 1;
        $args->search_target = $vars->search_target ? $vars->search_target : '';
        $args->search_keyword = $vars->search_keyword ? $vars->search_keyword : '';

        $output = HotopayModel::getBillingKeyList($args);
        Context::set('billingkey_list', $output->data);
        Context::set('total_count', $output->total_count);
        Context::set('total_page', $output->total_page);
        Context::set('page', $output->page);
        Context::set('page_navigation', $output->page_navigation);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('결제수단 목록 - Hotopay');
        $this->setTemplateFile('index_billingkey');
    }

    public function dispHotopayAdminInsertBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('결제수단 추가 - Hotopay');
        $this->setTemplateFile('insert_billingkey');
    }

    public function dispHotopayAdminUpdateBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
        $output = HotopayModel::getBillingKey($vars->key_idx);
        Context::set('billingkey', $output);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('결제수단 수정 - Hotopay');
        $this->setTemplateFile('update_billingkey');
    }

    public function dispHotopayAdminDeleteBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        // Context에 세팅
        Context::set('hotopay_config', $config);
		$output = HotopayModel::getBillingKey($vars->key_idx);
        Context::set('billingkey', $output);
        
        // 스킨 파일 지정
		Context::setBrowserTitle('결제수단 삭제 - Hotopay');
        $this->setTemplateFile('delete_billingkey');
    }
}
