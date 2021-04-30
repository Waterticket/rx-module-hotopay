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
	}
	
	/**
	 * 관리자 설정 화면 예제
	 */
	public function dispHotopayAdminConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);
		
		// 스킨 파일 지정
		Context::setBrowserTitle('모듈 설정 - Hotopay');
		$this->setTemplateFile('config');
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

	public function dispHotopayAdminPurchaseList()
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
}
