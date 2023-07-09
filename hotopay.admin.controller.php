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

		$config->hotopay_purchase_enabled = empty($vars->hotopay_purchase_enabled) ? 'N' : 'Y';
		$config->shop_name = $vars->shop_name;
		$config->purchase_term_url = $vars->purchase_term_url;
		$config->board_module_srl = $vars->board_module_srl;
		$config->point_discount = empty($vars->point_discount) ? 'N' : 'Y';
		$config->cart_item_limit = $vars->cart_item_limit;
		$config->min_product_price = $vars->min_product_price;
		$config->change_group_to_regular_when_pay = empty($vars->change_group_to_regular_when_pay) ? 'N' : 'Y';
		$config->associate_group_srl = $vars->associate_group_srl;
		$config->regular_group_srl = $vars->regular_group_srl;
		$config->hotopay_license_key = $vars->hotopay_license_key;

		$config->hotopay_currency_renew_api_type = $vars->hotopay_currency_renew_api_type;
		$config->fixer_io_api_key = $vars->fixer_io_api_key;

		$config->hotopay_billingkey_encryption = $vars->hotopay_billingkey_encryption;
		$config->hotopay_aws_kms_arn = $vars->hotopay_aws_kms_arn;
		
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

	public function procHotopayAdminInsertPaymentGatewayConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$config->toss_enabled = empty($vars->toss_enabled) ? 'N' : 'Y';
		$config->toss_payments_list = $vars->toss_payments_list ?? array();
		$config->toss_payments_client_key = $vars->toss_payments_client_key;
		$config->toss_payments_secret_key = $vars->toss_payments_secret_key;
		$config->toss_payments_install_month = $vars->toss_payments_install_month;
		$config->toss_payments_max_install_month = $vars->toss_payments_max_install_month;
		$config->toss_payments_widget_enabled = empty($vars->toss_payments_widget_enabled) ? 'N' : 'Y';
		$config->toss_payments_billing_enabled = empty($vars->toss_payments_billing_enabled) ? 'N' : 'Y';
		$config->toss_payments_billing_client_key = $vars->toss_payments_billing_client_key;
		$config->toss_payments_billing_secret_key = $vars->toss_payments_billing_secret_key;

		$config->paypal_enabled = empty($vars->paypal_enabled) ? 'N' : 'Y';
		$config->paypal_client_key = $vars->paypal_client_key;
		$config->paypal_secret_key = $vars->paypal_secret_key;

		$config->kakaopay_enabled = empty($vars->kakaopay_enabled) ? 'N' : 'Y';
		$config->kakaopay_admin_key = $vars->kakaopay_admin_key;
		$config->kakaopay_cid_key = $vars->kakaopay_cid_key;
		$config->kakaopay_cid_secret_key = $vars->kakaopay_cid_secret_key;
		$config->kakaopay_install_month = $vars->kakaopay_install_month;
		
		$config->iamport_enabled = empty($vars->iamport_enabled) ? 'N' : 'Y';
		$config->iamport_mid = $vars->iamport_mid;
		$config->iamport_rest_api_key = $vars->iamport_rest_api_key;
		$config->iamport_rest_api_secret = $vars->iamport_rest_api_secret;

		$config->inicis_enabled = empty($vars->inicis_enabled) ? 'N' : 'Y';
		$config->inicis_mid = $vars->inicis_mid;
		$config->inicis_list = $vars->inicis_list;

		$config->payple_enabled = empty($vars->payple_enabled) ? 'N' : 'Y';
		$config->payple_server = $vars->payple_server ?? 'demo';
		$config->payple_list = $vars->payple_list;
		$config->payple_cst_id = $vars->payple_cst_id;
		$config->payple_cust_key = $vars->payple_cust_key;
		$config->payple_refund_key = $vars->payple_refund_key;
		$config->payple_referer_domain = preg_replace('#^[^:/.]*[:/]+#i', '', $vars->payple_referer_domain);
		$config->payple_purchase_type = $vars->payple_purchase_type;
		$config->payple_billing_enabled = empty($vars->payple_billing_enabled) ? 'N' : 'Y';
		$config->payple_billing_payments_list = $vars->payple_billing_payments_list;

		$config->n_account_enabled = empty($vars->n_account_enabled) ? 'N' : 'Y';
		$config->n_account_string = $vars->n_account_string;
		
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
		$logged_info = Context::get('logged_info');

		$product_srl = $vars->product_srl ?: getNextSequence();

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->product_srl = $product_srl;
		$args->product_name = $vars->product_name;
		$args->product_des = $vars->product_des;
		$args->product_sale_price = $vars->product_sale_price;
		$args->product_original_price = $vars->product_original_price;
		$args->product_pic_src = '';
		$args->product_pic_srl = 0;
		$args->document_srl = $vars->document_srl ?: 0;
		$args->tax_rate = $vars->tax_rate ?: 0;
		$args->is_adult = ($vars->is_adult == 'Y') ? 'Y' : 'N';
		$args->is_billing = ($vars->is_billing == 'Y') ? 'Y' : 'N';
		$args->allow_use_point = ($vars->allow_use_point == 'Y') ? 'Y' : 'N';
		$args->market_srl = $vars->market_srl ?: 0;
		$auto_calc_price = $vars->auto_calc_price ?: 'N';

		if (empty($args->product_sale_price) || empty($args->product_original_price))
		{
			if ($auto_calc_price == 'Y')
			{
				$min_price = -1;
				foreach ($vars->sale_option as $item)
				{
					if ($min_price == -1 || $min_price > $item['price'])
					{
						$min_price = $item['price'];
					}
				}

				$args->product_sale_price = $min_price;
				$args->product_original_price = $min_price;
			}
			else
			{
				return $this->createObject(-1, "필수 값이 누락되었습니다.");
			}
		}

		if (($config->min_product_price > 0) && ($args->product_sale_price < $config->min_product_price) && ($this->user->is_admin !== 'Y')) {
			return $this->createObject(-1, "최소 판매가는 {$config->min_product_price}원 입니다.");
		}

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
		$args->product_option = ''; // 사용 안함
		$args->product_buyer_group = $vars->product_buyer_group;
		$args->extra_vars = serialize($vars->extra_vars ?? new stdClass());
		$args->regdate = time();

		executeQuery("hotopay.insertProduct", $args);

		foreach ($vars->sale_option as $item)
		{
			$item = (object) $item;
			$obj = new stdClass();
			$obj->option_srl = (!empty($item->option_srl)) ? $item->option_srl : getNextSequence();
			$obj->product_srl = $product_srl;
			$obj->title = $item->title;
			$obj->description = $item->description ?? "";
			$obj->price = $item->price;
			$obj->stock = $item->stock ?: 0;
			$obj->infinity_stock = $item->infinity_stock ?? "N";
			$obj->billing_infinity_stock = $item->infinity_stock ?? "N";
			$obj->billing_period_date = $item->billing_period_date ?: 30;
			$obj->status = 'visible';
			$obj->extra_vars = $item->extra_vars ? serialize((object) $item->extra_vars) : serialize(new stdClass());
			$obj->regdate = time();

			$item_output = executeQuery("hotopay.insertProductOption", $obj);
			if(!$item_output->toBool())
			{
				return $item_output;
			}
		}

		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
		return $args;
	}

	public function procHotopayAdminPurchaseStatusChange()
	{
		$vars = Context::getRequestVars();
		$oHotopayController = getController('hotopay');
		$oHotopayModel = getModel('hotopay');

		$status = $vars->status; //"DONE"
		$purchase_srl = $vars->purchase_srl;

		$purchase_id = $vars->purchase_id;
		if(isset($purchase_id) && empty($purchase_srl))
		{
			$purchase_srl = substr($purchase_id, 2); // HTxxxx 형식일경우 자르기
		}

		$purchase_data = $oHotopayModel->getPurchase($purchase_srl);

		if(strcmp($status, "DONE") === 0)
		{
			$oHotopayController->_ActivePurchase($purchase_srl, $purchase_data->member_srl);
		}
		else if(strcmp($status, "CANCEL") === 0)
		{
			$cancel_reason = $vars->cancel_reason;
			$cancel_amount = $vars->cancel_amount ?? -1;
			$bank_info = array(
                "bank" => $vars->bank,
                "accountNumber" => $vars->accountNumber,
                "holderName" => $vars->holderName,
            );

			$output = $oHotopayController->_CancelPurchase($purchase_srl, $cancel_reason, $cancel_amount, $bank_info);
		}

		if($output->error != 0)
            return $this->createObject(-1, $output->message);

		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url') ?? getNotEncodedUrl("","module","admin","act","dispHotopayAdminPurchaseList"));
	}

	public function procHotopayAdminInsertPurchase()
	{
		$vars = Context::getRequestVars();
		$oHotopayController = getController('hotopay');
		$oHotopayModel = getModel('hotopay');

		return new BaseObject(-1, '준비중입니다.');

		$product = $oHotopayModel->getProduct($vars->product_srl);
		$order_id = getNextSequence();
		$title = $product->product_name;
		$target_member_srl = $vars->target_member_srl;
		$purchase_price = $vars->purchase_price;
		$product_srl = $vars->product_srl;
		$option = $vars->option_srl;
		$date = strtotime($vars->purchase_date);
		$extra_vars = $vars->extra_vars;

		$args = new stdClass();
		$args->purchase_srl = $order_id;
		$args->member_srl = $target_member_srl;
		$args->title = $title;
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
		$args->extra_vars = serialize($extra_vars ?? new stdClass());
		$args->regdate = $date;

		executeQuery('hotopay.insertPurchase', $args);

		$oHotopayController->_ActivePurchase($order_id, $target_member_srl); // 결제 물품 활성화

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
		$args->document_srl = $vars->document_srl ?: 0;
		$args->tax_rate = $vars->tax_rate ?: 0;
		$args->is_adult = ($vars->is_adult == 'Y') ? 'Y' : 'N';
		$args->is_billing = ($vars->is_billing == 'Y') ? 'Y' : 'N';
		$args->allow_use_point = ($vars->allow_use_point == 'Y') ? 'Y' : 'N';
		$args->extra_vars = serialize($vars->extra_vars ?? new stdClass());
		$auto_calc_price = $vars->auto_calc_price ?: 'N';

		if ($auto_calc_price == 'Y')
		{
			$min_price = -1;
			foreach ($vars->sale_option as $item)
			{
				if ($min_price == -1 || $min_price > $item['price'])
				{
					$min_price = $item['price'];
				}
			}

			$args->product_sale_price = $min_price;
			$args->product_original_price = $min_price;
		}

		if (($config->min_product_price > 0) && ($args->product_sale_price < $config->min_product_price) && ($this->user->is_admin !== 'Y')) {
			return $this->createObject(-1, "최소 판매가는 {$config->min_product_price}원 입니다.");
		}

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
		$args->product_option = ''; // 사용 안함
		$args->product_buyer_group = $vars->product_buyer_group ?: 0;
		$args->regdate = time();

		$output = executeQuery("hotopay.updateProduct", $args);
		if(!$output->toBool()) return $output;

		$cache_key = 'hotopay:product:' . $vars->product_srl;
        Rhymix\Framework\Cache::delete($cache_key);

		$oHotopayModel = getModel('hotopay');
		$options = $oHotopayModel->getProductOptions($vars->product_srl);

		foreach ($vars->sale_option as $item)
		{
			$item = (object) $item;
			$obj = new stdClass();
			$obj->option_srl = ($item->option_srl != 0) ? $item->option_srl : getNextSequence();
			$obj->product_srl = $vars->product_srl;
			$obj->title = $item->title;
			$obj->description = $item->description ?? "";
			$obj->price = $item->price;
			$obj->stock = $item->stock ?: 0;
			$obj->infinity_stock = $item->infinity_stock ?? "N";
			$obj->billing_infinity_stock = $item->infinity_stock ?? "N";
			$obj->billing_period_date = $item->billing_period_date ?: 30;
			$obj->status = 'visible';
			$obj->extra_vars = $item->extra_vars ? serialize((object) $item->extra_vars) : serialize(new stdClass());
			$obj->regdate = time();

			if($item->option_srl == 0)
			{
				$item_output = executeQuery("hotopay.insertProductOption", $obj);
			}
			else
			{
				$item_output = executeQuery("hotopay.updateProductOption", $obj);
			}
			
			if(!$item_output->toBool())
			{
				return $item_output;
			}

			unset($options[$obj->option_srl]);
		}

		// 삭제된 옵션들
		$delete_options = [];
		foreach ($options as $option)
		{
			$delete_options[] = $option->option_srl;
		}

		if(!empty($delete_options))
		{
			$obj = new stdClass();
			$obj->option_srl = $delete_options;
			$obj->status = 'deleted';
			$output = executeQuery("hotopay.updateProductOptionStatus", $obj);
			if(!$output->toBool())
			{
				return $output;
			}
		}

		$cache_key = 'hotopay:product:' . $vars->product_srl;
        Rhymix\Framework\Cache::delete($cache_key);
		
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

		$cache_key = 'hotopay:product:' . $vars->product_srl;
        Rhymix\Framework\Cache::delete($cache_key);

		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('','mid','admin','act','dispHotopayAdminProductList'));
	}

	public function procHotopayAdminInsertNotification()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();

		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$config->purchase_success_notification_method = $vars->purchase_success_notification_method ?? array();
		$config->purchase_success_notification_message_note_title = $vars->purchase_success_notification_message_note_title;
		$config->purchase_success_notification_message_note = $vars->purchase_success_notification_message_note;
		$config->purchase_success_notification_message_mail_title = $vars->purchase_success_notification_message_mail_title;
		$config->purchase_success_notification_message_mail = $vars->purchase_success_notification_message_mail;
		$config->purchase_success_notification_message_sms = $vars->purchase_success_notification_message_sms;

		$config->purchase_account_notification_method = $vars->purchase_account_notification_method ?? array();
		$config->purchase_account_notification_message_note_title = $vars->purchase_account_notification_message_note_title;
		$config->purchase_account_notification_message_note = $vars->purchase_account_notification_message_note;
		$config->purchase_account_notification_message_mail_title = $vars->purchase_account_notification_message_mail_title;
		$config->purchase_account_notification_message_mail = $vars->purchase_account_notification_message_mail;
		$config->purchase_account_notification_message_sms = $vars->purchase_account_notification_message_sms;

		$config->purchase_refund_notification_method = $vars->purchase_refund_notification_method ?? array();
		$config->purchase_refund_notification_message_note_title = $vars->purchase_refund_notification_message_note_title;
		$config->purchase_refund_notification_message_note = $vars->purchase_refund_notification_message_note;
		$config->purchase_refund_notification_message_mail_title = $vars->purchase_refund_notification_message_mail_title;
		$config->purchase_refund_notification_message_mail = $vars->purchase_refund_notification_message_mail;
		$config->purchase_refund_notification_message_sms = $vars->purchase_refund_notification_message_sms;

		$config->admin_mailing = empty($vars->admin_mailing) ? 'N' : 'Y';
		$config->admin_mailing_status = $vars->admin_mailing_status ?? array();

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

    public function dispHotopayAdminSubscriptionIndex() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        
        // Context에 세팅
        Context::set('config', $config);

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
        $this->setTemplateFile('index_subscription');
    }

    public function procHotopayAdminInsertSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        $args = new stdClass();
        if(!empty($vars->subscription_srl) || ($vars->subscription_srl === 0)) $args->subscription_srl = $vars->subscription_srl;
        if(!empty($vars->member_srl) || ($vars->member_srl === 0)) $args->member_srl = $vars->member_srl;
        if(!empty($vars->product_srl) || ($vars->product_srl === 0)) $args->product_srl = $vars->product_srl;
        if(!empty($vars->option_srl) || ($vars->option_srl === 0)) $args->option_srl = $vars->option_srl;
        if(!empty($vars->quantity) || ($vars->quantity === 0)) $args->quantity = $vars->quantity;
        if(!empty($vars->price) || ($vars->price === 0)) $args->price = $vars->price;
        if(!empty($vars->billing_key_idx) || ($vars->billing_key_idx === 0)) $args->billing_key_idx = $vars->billing_key_idx;
        if(!empty($vars->period) || ($vars->period === 0)) $args->period = $vars->period;
        if(!empty($vars->register_date) || ($vars->register_date === 0)) $args->register_date = $vars->register_date;
        if(!empty($vars->last_billing_date) || ($vars->last_billing_date === 0)) $args->last_billing_date = $vars->last_billing_date;
        if(!empty($vars->esti_billing_date) || ($vars->esti_billing_date === 0)) $args->esti_billing_date = $vars->esti_billing_date;
        if(!empty($vars->status) || ($vars->status === 0)) $args->status = $vars->status;
        HotopayModel::insertSubscription($args);

        $this->setMessage('success_registed');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminSubscriptionIndex'));
    }

    public function procHotopayAdminUpdateSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        $args = new stdClass();
        $args->subscription_srl = $vars->subscription_srl;
        $args->member_srl = $vars->member_srl;
        $args->product_srl = $vars->product_srl;
        $args->option_srl = $vars->option_srl;
        $args->quantity = $vars->quantity;
        $args->price = $vars->price;
        $args->billing_key_idx = $vars->billing_key_idx;
        $args->period = $vars->period;
        $args->register_date = $vars->register_date;
        $args->last_billing_date = $vars->last_billing_date;
        $args->esti_billing_date = $vars->esti_billing_date;
        $args->status = $vars->status;
        HotopayModel::updateSubscription($args);

        $this->setMessage('success_updated');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminSubscriptionIndex'));
    }

    public function procHotopayAdminDeleteSubscription() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        HotopayModel::deleteSubscription($vars->subscription_srl);

        $this->setMessage('success_deleted');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminSubscriptionIndex'));
    }

    public function procHotopayAdminInsertBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        $args = new stdClass();
        if(!empty($vars->key_idx) || ($vars->key_idx === 0)) $args->key_idx = $vars->key_idx;
        if(!empty($vars->member_srl) || ($vars->member_srl === 0)) $args->member_srl = $vars->member_srl;
        if(!empty($vars->pg) || ($vars->pg === 0)) $args->pg = $vars->pg;
        if(!empty($vars->type) || ($vars->type === 0)) $args->type = $vars->type;
        if(!empty($vars->key) || ($vars->key === 0)) $args->key = $vars->key;
        if(!empty($vars->key_hash) || ($vars->key_hash === 0)) $args->key_hash = $vars->key_hash;
        if(!empty($vars->payment_type) || ($vars->payment_type === 0)) $args->payment_type = $vars->payment_type;
        if(!empty($vars->alias) || ($vars->alias === 0)) $args->alias = $vars->alias;
        if(!empty($vars->number) || ($vars->number === 0)) $args->number = $vars->number;
        if(!empty($vars->regdate) || ($vars->regdate === 0)) $args->regdate = $vars->regdate;
        HotopayModel::insertBillingKey($args);

        $this->setMessage('success_registed');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminBillingKeyIndex'));
    }

    public function procHotopayAdminUpdateBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        $args = new stdClass();
        if(!empty($vars->key_idx) || ($vars->key_idx === 0)) $args->key_idx = $vars->key_idx;
        if(!empty($vars->member_srl) || ($vars->member_srl === 0)) $args->member_srl = $vars->member_srl;
        if(!empty($vars->pg) || ($vars->pg === 0)) $args->pg = $vars->pg;
        if(!empty($vars->type) || ($vars->type === 0)) $args->type = $vars->type;
        if(!empty($vars->key) || ($vars->key === 0)) $args->key = $vars->key;
        if(!empty($vars->key_hash) || ($vars->key_hash === 0)) $args->key_hash = $vars->key_hash;
        if(!empty($vars->payment_type) || ($vars->payment_type === 0)) $args->payment_type = $vars->payment_type;
        if(!empty($vars->alias) || ($vars->alias === 0)) $args->alias = $vars->alias;
        if(!empty($vars->number) || ($vars->number === 0)) $args->number = $vars->number;
        if(!empty($vars->regdate) || ($vars->regdate === 0)) $args->regdate = $vars->regdate;
        HotopayModel::updateBillingKey($args);

        $this->setMessage('success_updated');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminBillingKeyIndex'));
    }

    public function procHotopayAdminDeleteBillingKey() 
    {
        // 현재 설정 상태 불러오기
        $config = $this->getConfig();
        $vars = Context::getRequestVars();
        
        HotopayModel::deleteBillingKey($vars->key_idx);

        $this->setMessage('success_deleted');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHotopayAdminBillingKeyIndex'));
    }
}
