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

		$cart_items = HotopayModel::getCartItems($member_srl);
		Context::set('cart_items', $cart_items);

		if (empty($cart_items))
		{
			$this->setError(-1);
			$this->setMessage('장바구니에 상품이 없습니다.');
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', 'hotopay', 'act', 'dispHotopayCart'));
			return;
		}

		$product_srls = array();
		foreach ($cart_items as $cart_item)
		{
			$product_srls[] = $cart_item->product_srl;
		}
		Context::set('product_id', $product_srls, true);

		try
		{
			$this->dispHotopayOrderPage();
		}
		catch(Exception $e)
		{
			$this->setError(-1);
			$this->setMessage($e->getMessage());
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', 'hotopay', 'act', 'dispHotopayCart'));
			return;
		}

		$purchase_price = 0;
		foreach ($cart_items as $item)
		{
			$purchase_price += ($item->option_price + ($item->option_price * $item->tax_rate/100)) * $item->quantity;
		}
		Context::set('purchase_price', $purchase_price);

		$filtered_password_keys = array();
		$filtered_billing_keys = array();

		$billing_keys = HotopayModel::getBillingKeys($member_srl);
		foreach ($billing_keys as $key)
		{
			if ($key->type == 'password')
			{
				$filtered_password_keys[] = $key;
			}
			else if ($key->type == 'billing' && $key->pg == 'toss')
			{
				$filtered_billing_keys[] = $key;
			}
		}

		Context::set('password_keys', $filtered_password_keys);
		Context::set('billing_keys', $filtered_billing_keys);

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

		Context::set('toss_billing_enabled', $config->toss_payments_billing_enabled == 'Y');
		Context::set('payple_billing_enabled', $config->payple_billing_enabled == 'Y');

		if (empty($vars->product_id))
		{
			throw new \Rhymix\Framework\Exception('결제할 상품을 선택해주세요.');
		}

		if (!is_array($vars->product_id))
		{
			$vars->product_id = array($vars->product_id);
		}

		$product_list = HotopayModel::getProducts($vars->product_id);
		Context::set('product_list', $product_list);

		$extra_info_list = HotopayModel::getProductExtraInfo(array_merge($vars->product_id, array(0)));
		Context::set('extra_info_list', $extra_info_list);

		$is_non_billing_product_exist = false;
		$is_billing_product_exist = false;
		$point_discount_allow = ($config->point_discount == 'Y');
		foreach ($product_list as $product)
		{
			if ($product->is_billing == 'Y')
			{
				$is_billing_product_exist = true;
				$point_discount_allow = false;
			}
			else
			{
				$is_non_billing_product_exist = true;
			}

			if ($product->allow_use_point != 'Y')
			{
				$point_discount_allow = false;
			}
		}

		Context::set('point_discount_allow', $point_discount_allow);

		if ($is_billing_product_exist && $is_non_billing_product_exist)
		{
			throw new \Rhymix\Framework\Exception('정기결제 상품과 일반결제 상품을 동시에 구매할 수 없습니다.');
		}

		if ($is_billing_product_exist)
		{
			$billing_keys = HotopayModel::getBillingKeys($this->user->member_srl);
			Context::set('billing_keys', $billing_keys);
			Context::set('purchase_type', 'billing');
		}
		else
		{
			Context::set('purchase_type', 'normal');
		}

		$logged_info = Context::get('logged_info');
		$oPointModel = PointModel::getInstance();
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

		$member_srl = $logged_info->member_srl;

		$page = $vars->page ?: 1;
		$size = 20;
		$offset = ($page - 1) * $size;

		$oDB = DB::getInstance();
		$total_size_query = $oDB->query("SELECT COUNT(*) AS count FROM `hotopay_purchase` WHERE `member_srl` = ?", [$member_srl]);
		[$total_size_object] = $total_size_query->fetchAll();
		$total_size = (int) $total_size_object->count;

		if ($total_size > 0)
		{
			$stmt = $oDB->prepare("SELECT 
				`purchase`.`purchase_srl`,
				`purchase`.`member_srl`,
				`purchase`.`title`,
				`purchase`.`pay_method`,
				`purchase`.`product_purchase_price`,
				`purchase`.`product_original_price`,
				`purchase`.`pay_status`,
				`purchase`.`receipt_url`,
				`purchase`.`is_billing`,
				`purchase`.`regdate`,

				`item`.`item_srl`,
				`item`.`option_srl`     AS `item_option_srl`,
				`item`.`option_name`    AS `item_option_name`,
				`item`.`purchase_price` AS `item_purchase_price`,
				`item`.`quantity`       AS `item_quantity`,
				`product`.`product_name`,
				`product`.`product_pic_src`,
				`product`.`market_srl`,
				`product`.`member_srl`  AS `seller_member_srl`,
				`product`.`document_srl`

				FROM `hotopay_purchase` AS `purchase` 
				INNER JOIN (
					SELECT purchase_srl FROM `hotopay_purchase` AS `purchase_inner`
						WHERE `purchase_inner`.`member_srl` = :member_srl
						ORDER BY `purchase_inner`.`purchase_srl` DESC
						LIMIT :offset, :size
				) AS purchase_inner	ON `purchase_inner`.`purchase_srl` = `purchase`.`purchase_srl`

				LEFT JOIN `hotopay_purchase_item` AS `item`
					ON `purchase`.`purchase_srl` = `item`.`purchase_srl`
				LEFT JOIN `hotopay_product` AS `product`
					ON `item`.`product_srl` = `product`.`product_srl`");

			$stmt->bindValue(':member_srl', $member_srl, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->bindValue(':size', $size, PDO::PARAM_INT);

			try {
				$stmt->execute();
			} catch (Exception $ignore) {
				return $this->createObject(-1, "Query Error (code: 1001)");
			}
			$purchase_list_before = $stmt->fetchAll();
			$purchase_list_by_idx = array();

			foreach ($purchase_list_before as $purchase_item)
			{
				if (!isset($purchase_list_by_idx[$purchase_item->purchase_srl]))
				{
					$purchase_list_by_idx[$purchase_item->purchase_srl] = (object) array(
						'purchase_srl' => $purchase_item->purchase_srl,
						'member_srl' => $purchase_item->member_srl,
						'title' => $purchase_item->title,
						'pay_method' => $purchase_item->pay_method,
						'product_purchase_price' => $purchase_item->product_purchase_price,
						'product_original_price' => $purchase_item->product_original_price,
						'pay_status' => $purchase_item->pay_status,
						'receipt_url' => $purchase_item->receipt_url,
						'is_billing' => $purchase_item->is_billing,
						'regdate' => $purchase_item->regdate,
						'items' => array(),
					);
				}

				$purchase_list_by_idx[$purchase_item->purchase_srl]->items[] = (object) array(
					'item_srl' => $purchase_item->item_srl,
					'option_srl' => $purchase_item->item_option_srl,
					'option_name' => $purchase_item->item_option_name,
					'purchase_price' => $purchase_item->item_purchase_price,
					'quantity' => $purchase_item->item_quantity,
					'product_name' => $purchase_item->product_name,
					'product_pic_src' => $purchase_item->product_pic_src,
					'market_srl' => $purchase_item->market_srl,
					'seller_member_srl' => $purchase_item->seller_member_srl,
					'document_srl' => $purchase_item->document_srl,
				);
			}

			$purchase_list = array_values($purchase_list_by_idx);
		}
		else
		{
			$purchase_list = array();
		}

		$obj = new stdClass();
		$obj->member_srl = $member_srl;
		$obj->page = $page;
		$obj->size = $size;
		$obj->total_size = $total_size;
		$obj->purchase_list = $purchase_list;
		ModuleHandler::triggerCall('hotopay.displayOrderList', 'before', $obj);

		Context::set('page', $page);
		Context::set('size', $size);
		Context::set('total_size', $total_size);
		Context::set('query_count', $size);
		Context::set('list_count', count($purchase_list));
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

		$cart_items = HotopayModel::getCartItems($member_srl);
		Context::set('cart_items', $cart_items);

		$this->setTemplateFile('cart');
	}
}
