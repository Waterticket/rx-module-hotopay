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
		$this->setTemplateFile('config');
	}

	public function dispHotopayAdminProductList()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// Context에 세팅
		Context::set('hotopay_config', $config);


		$products = executeQueryArray('hotopay.getProducts');
		Context::set('products', $products->data); // 상품 데이터 추가
		
		// 스킨 파일 지정
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
		$this->setTemplateFile('insert_product');
	}
}
