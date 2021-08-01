<?php

/**
 * Hoto Pay
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayAdminController extends Hotopay
{
	/**
	 * 관리자 설정 저장 액션 예제
	 */
	public function procHotopayAdminInsertConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();
		
		$config->toss_enabled = empty($vars->toss_enabled) ? 'N' : 'Y';
		$config->toss_payments_client_key = $vars->toss_payments_client_key;
		$config->toss_payments_secret_key = $vars->toss_payments_secret_key;

		$config->paypal_enabled = empty($vars->paypal_enabled) ? 'N' : 'Y';
		$config->paypal_client_key = $vars->paypal_client_key;
		$config->paypal_secret_key = $vars->paypal_secret_key;

		$config->n_account_enabled = empty($vars->n_account_enabled) ? 'N' : 'Y';
		$config->n_account_string = $vars->n_account_string;
		$config->purchase_term_url = $vars->purchase_term_url;
		
		// 변경된 설정을 저장
		$output = $this->setConfig($config);
		if (!$output->toBool())
		{
			return $output;
		}
		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	public function procHotopayAdminInsertProduct()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$product_srl = getNextSequence();

		$args = new stdClass();
		$args->product_srl = $product_srl;
		$args->product_name = $vars->product_name;
		$args->product_des = $vars->product_des;
		$args->product_sale_price = $vars->product_sale_price;
		$args->product_original_price = $vars->product_original_price;
		$args->product_pic_src = '';
		$args->product_pic_srl = 0;

        $allow_mime_type = array('image/jpeg', 'image/png', 'image/gif');
		$upfile = $vars->product_pic;
		if(!empty($upfile)){
            if(!in_array($upfile['type'], $allow_mime_type)){
                return $this->createObject(-1, "file ext error");
            }

			// 기존에 파일이 있을 경우
			// $oFileController = getController('file');
            // $mainstream_srl = json_decode(base64_decode(Context::get('guild_logo_urls')))->tg_srl;
			// $oFileController->deleteFiles($mainstream_srl);

			$module_info = Context::get("module_info");
			$module_srl = $module_info->module_srl;
            $upload_target_srl = getNextSequence();

			$oFileController = getController('file');
			$output = $oFileController->insertFile($upfile, $module_srl, $upload_target_srl,0,true);
			$args->product_pic_src = $output->get('uploaded_filename');
			$args->product_pic_srl = $upload_target_srl;
            
            $oFileController->setFilesValid($upload_target_srl);
        }else{
			$product_pic_org_srl = Context::get('product_pic_org_srl');
			$product_pic_org_src = Context::get('product_pic_org_src');

			if(empty($product_pic_org_srl) || empty($product_pic_org_src)) // 물품 수정이 아니라면 + 이미지를 업로드 하지 않았다면
			{
				$args->product_pic_src = './modules/hotopay/skins/default/img/no_image.jpg'; // No Image
            	$args->product_pic_srl = 0;
			}
			else
			{
				$args->product_pic_src = Context::get('product_pic_org_src');
				$args->product_pic_srl = Context::get('product_pic_org_srl');
			}
        }

		// \n을 <br>로 변환하는 함수
		// $contents = nl2br($vars->product_option);
		$args->product_option = $vars->product_option;
		$args->product_buyer_group = $vars->product_buyer_group;
		$args->regdate = time();

		executeQuery("hotopay.insertProduct", $args);
		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	public function procHotopayAdminPurchaseStatusChange()
	{
		$vars = Context::getRequestVars();
		$oHotopayController = getController('hotopay');
		$oHotopayModel = getModel('hotopay');

		$status = $vars->status; //"DONE"
		$purchase_srl = $vars->purchase_srl;
		$purchase_data = $oHotopayModel->getProduct($product_srl);

		if(strcmp($status, "DONE") === 0)
		{
			$oHotopayController->_ActivePurchase($purchase_srl, $purchase_data->member_srl);
		}
		else if(strcmp($status, "CANCEL") === 0)
		{
			// @todo 취소 코드 짜기
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl("","module","admin","act","dispHotopayAdminPurchaseList"));
	}

	public function procHotopayAdminInsertPurchase()
	{
		$vars = Context::getRequestVars();
		$oHotopayController = getController('hotopay');
		$oHotopayModel = getModel('hotopay');

		$product = $oHotopayModel->getProduct($vars->product_srl);
		$order_id = getNextSequence();
		$title = $product->product_name;
		$target_member_srl = $vars->target_member_srl;
		$purchase_price = $vars->purchase_price;
		$product_srl = $vars->product_srl;
		$option = $vars->option_srl;
		$date = strtotime($vars->purchase_date);

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $target_member_srl;
		$args->products = json_encode(array(
			"t" => $title,
			"bp" => array($product_srl),
			"opt" => array($product_srl => $option)
		));
		$args->pay_method = 'n_account';
		$args->product_purchase_price = $purchase_price;
		$args->product_original_price = $product->product_sale_price;
		$args->pay_status = 'DONE';
		$args->pay_data = '';
		$args->regdate = $date;

		executeQuery('hotopay.insertPurchase', $args);

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl("","module","admin","act","dispHotopayAdminInsertPurchase"));
	}

	public function procHotopayAdminModifyProduct()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$args = new stdClass();
		$args->product_srl = $vars->product_srl;
		$args->product_name = $vars->product_name;
		$args->product_des = $vars->product_des;
		$args->product_sale_price = $vars->product_sale_price;
		$args->product_original_price = $vars->product_original_price;
		$args->product_pic_src = $vars->product_pic_org_src;
		$args->product_pic_srl = $vars->product_pic_org_srl;

        $allow_mime_type = array('image/jpeg', 'image/png', 'image/gif');
		$upfile = $vars->product_pic;
		if(!empty($upfile)){
            if(!in_array($upfile['type'], $allow_mime_type)){
                return $this->createObject(-1, "file ext error");
            }

			// 기존에 파일이 있을 경우
			// $oFileController = getController('file');
            // $mainstream_srl = json_decode(base64_decode(Context::get('guild_logo_urls')))->tg_srl;
			// $oFileController->deleteFiles($mainstream_srl);

			$module_info = Context::get("module_info");
			$module_srl = $module_info->module_srl;
            $upload_target_srl = getNextSequence();

			$oFileController = getController('file');
			$output = $oFileController->insertFile($upfile, $module_srl, $upload_target_srl,0,true);
			$args->product_pic_src = $output->get('uploaded_filename');
			$args->product_pic_srl = $upload_target_srl;
            
            $oFileController->setFilesValid($upload_target_srl);
			if($vars->product_pic_org_srl != 0 || !empty($vars->remove_img)) $oFileController->deleteFile($vars->product_pic_org_srl);
        }else{
			$product_pic_org_srl = Context::get('product_pic_org_srl');
			$product_pic_org_src = Context::get('product_pic_org_src');

			if(empty($product_pic_org_srl) || empty($product_pic_org_src) || !empty($vars->remove_img)) // 물품 수정이 아니라면 or 이미지를 업로드 하지 않았다면 or 이미지 제거에 체크했다면
			{
				$args->product_pic_src = './modules/hotopay/skins/default/img/no_image.jpg'; // No Image
            	$args->product_pic_srl = 0;
			}
        }

		// \n을 <br>로 변환하는 함수
		// $contents = nl2br($vars->product_option);
		$args->product_option = $vars->product_option;
		$args->product_buyer_group = $vars->product_buyer_group ?: 0;
		$args->regdate = time();

		executeQuery("hotopay.updateProduct", $args);
		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	public function procHotopayAdminDeleteProduct()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$args = new stdClass();
		$args->product_srl = $vars->product_srl;
		executeQuery('hotopay.deleteProduct', $args);

		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(getUrl('','mid','admin','act','dispHotopayAdminProductList'));
	}
}
