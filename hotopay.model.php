<?php

/**
 * Hoto Pay
 *
 * Copyright (c) Waterticket
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayModel extends Hotopay
{
    public static function getProduct(int $product_srl)
    {
		$cache_key = 'hotopay:product:' . $product_srl;
		$product = Rhymix\Framework\Cache::get($cache_key);
        if(!$product->product_srl) {
            $args = new stdClass();
            $args->product_srl = $product_srl;
            $output = executeQuery('hotopay.getProducts', $args);

            if(!$output->toBool() || empty($output->data))
            {
                return new \BaseObject(-1, "Product does not exist.");
            }

            $output->data->extra_vars = unserialize($output->data->extra_vars);
            $product = $output->data;
        }

        $product->product_option = self::getProductOptions($product_srl);

        Rhymix\Framework\Cache::set($cache_key, $product);
        return $product;
    }

    public static function getProducts(array $product_srls)
    {
        $args = new stdClass();
        $args->product_srl = $product_srls;
        $output = executeQueryArray('hotopay.getProducts', $args);
        self::updateExpiredPurchaseStatus();

        if(!$output->toBool() || empty($output->data))
        {
            return new \BaseObject(-1, "Product does not exist.");
        }

        foreach($output->data as &$val)
        {
            $cache_key = 'hotopay:product:' . $val->product_srl;
            $cache = Rhymix\Framework\Cache::get($cache_key);
            if($cache)
            {
                $cache->product_option = self::getProductOptions($val->product_srl);
                $val = $cache;
                continue;
            }

            $val->product_option = self::getProductOptions($val->product_srl);
            $val->extra_vars = unserialize($val->extra_vars);
            Rhymix\Framework\Cache::set($cache_key, $val);
        }

        return $output->data;
    }

    public static function getProductsAll()
    {
        $output = executeQueryArray('hotopay.getProductsAll');

        if(!$output->toBool() || empty($output->data))
        {
            return new \BaseObject(-1, "Product does not exist.");
        }

        return $output->data;
    }

	public static function getProductOptions($product_srl)
    {
        $args = new stdClass();
        $args->product_srl = $product_srl;
        $args->status = ['visible'];
        $output = executeQueryArray('hotopay.getProductOptions', $args);

        if(!$output->toBool())
        {
            return $output;
        }

        $product_options = array();

        foreach($output->data as $key => $val)
        {
            $val->extra_vars = unserialize($val->extra_vars);
            $product_options[$val->option_srl] = $val;
        }

        return $product_options;
    }

    public static function getPurchase($purchase_srl)
    {
        $args = new stdClass();
        $args->purchase_srl = $purchase_srl;
        $purchase = executeQuery('hotopay.getPurchase', $args);
        if(!$purchase->toBool())
        {
            return new \BaseObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        return $purchase->data;
    }

    public static function getPurchaseItems($purchase_srl)
    {
        $args = new stdClass();
        $args->purchase_srl = $purchase_srl;
        $output = executeQueryArray('hotopay.getPurchaseItem', $args);
        if(!$output->toBool())
        {
            return new \BaseObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        return $output->data;
    }

    public static function getProductsByPurchaseSrl($purchase_srl)
    {
        $items = self::getPurchaseItems($purchase_srl);
        $products = array();
        foreach($items as $item)
        {
            $products[] = $item->product_srl;
        }

        return self::getProducts($products);
    }

    public static function getOptionsByPurchaseSrl($purchase_srl)
    {
        $items = self::getPurchaseItems($purchase_srl);
        $option_srls = [];
        foreach ($items as $item)
        {
            $option_srls[] = $item->option_srl;
        }

        $args = new stdClass();
        $args->option_srl = $option_srls;
        $output = executeQueryArray('hotopay.getOptions', $args);
        if(!$output->toBool())
        {
            return new \BaseObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        $option_data = [];
        foreach ($output->data as $val)
        {
            $val->extra_vars = unserialize($val->extra_vars);
            $option_data[$val->product_srl] = $val;
        }

        return $option_data;
    }

    public static function getOption(int $option_srl): object
    {
        $args = new stdClass();
        $args->option_srl = $option_srl;
        $output = executeQuery('hotopay.getOptions', $args);
        if(!$output->toBool())
        {
            return new \BaseObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        $output->data->extra_vars = unserialize($output->data->extra_vars);

        return $output->data;
    }

    public static function payStatusCodeToString($code)
    {
        switch($code)
        {
            case 'PENDING':
                return "결제 대기중";
                break;

            case 'WAITING_FOR_DEPOSIT':
                return "입금 대기중";
                break;

            case 'DONE':
                return "결제 완료";
                break;

            case "CANCELED":
                return "결제 취소";
                break;

            case "EXPIRED":
                return "결제 만료";
                break;

            case "FAILED":
                return "결제 실패";
                break;

            case "REFUNDED":
                return "환불됨";
                break;

            default:
                return $code;
                break;
        }
    }

    public static function purchaseMethodToString($pay_method)
    {
        switch($pay_method)
        {
            case "n_account":
                return "무통장 입금";

            case "ts_account":
                return "계좌이체";

            case "v_account":
                return "가상계좌";

            case "card":
                return "신용카드";

            case "voucher":
                return "문화상품권";

            case "cellphone":
                return "휴대폰";

            case "paypal":
                return "페이팔";

            case "kakaopay":
                return "카카오페이";

            case "gpay":
                return "구글 페이";

            case "toss":
                return "토스앱";

            case "toss_paypal":
                return "토스해외간편결제(페이팔)";

            case "inic_card":
                return "신용카드";

            case "inic_trans":
                return "실시간계좌이체";

            case "inic_vbank":
                return "가상계좌";

            case "inic_phone":
                return "휴대폰소액결제";

            case "inic_cultureland":
                return "문화상품권";

            case "inic_smartculture":
                return "스마트문상";

            case "inic_happymoney":
                return "해피머니";

            case "paypl_card":
                return "신용카드";

            case "paypl_transfer":
                return "계좌이체";

            case "point":
                return "포인트";

            default:
                return $pay_method;
        }
    }

    public static function stringCut($str,$length)
    {
        $result = "";

        if(mb_strlen($str) > $length) {
            $result = mb_substr($str, 0, $length - 1);
            $result = $result."...";
        }else{
            $result = $str;
        }

        return $result;
    }

    public function changeMessageRegisterKey($string, $purchase = null)
    {
        $config = $this->getConfig();
        if($purchase == null) $purchase = new stdClass();
        $pay_data = json_decode($purchase->pay_data);
        $account = '';

        if(isset($pay_data->virtualAccount->accountNumber))
            $account = $pay_data->virtualAccount->bank.' '.$pay_data->virtualAccount->accountNumber;

        if($purchase->pay_method == 'n_account')
        {
            $n_account_arr = preg_split('/\r\n|\r|\n/', $config->n_account_string);
            $account = $n_account_arr[0];
        }

        if ($purchase->pay_method == 'inic_vbank')
        {
            $account = $pay_data->vbank_name.' '.$pay_data->vbank_num.' '.$pay_data->vbank_holder;
        }

        $string = str_replace("[쇼핑몰명]", $config->shop_name, $string);
        $string = str_replace("[상품명]", mb_substr($purchase->title, 0, 50), $string);
        $string = str_replace("[주문확인링크]", '<a href="'.mb_substr(Context::getDefaultUrl(),0,-1).getUrl("","mid","hotopay","act","dispHotopayOrderList").'" target="_blank" title="주문 확인하기">[주문 확인하기]</a>', $string);
        $string = str_replace("[계좌번호]", $account, $string);
        $string = str_replace("[주문금액]", number_format($purchase->product_purchase_price), $string);
        $string = str_replace("[주문번호]", 'HT'.$purchase->purchase_srl, $string);

        return $string;
    }

    public static function changeCurrency(string $original_currency, string $change_currency, $amount, int $round = 2)
    {
        if ($round > 5) $round = 5;

        $original_to_change_query = executeQuery('hotopay.getCurrency', ['base_currency' => $original_currency, 'target_currency' => $change_currency]);
        if (!empty($original_to_change_query->data->base_currency))
        {
            $base = $original_to_change_query->data->base_value;
            $target = $original_to_change_query->data->target_value;

            return round(($amount / $base) * $target, $round);
        }

        $usd_to_original = executeQuery('hotopay.getCurrency', ['base_currency' => 'USD', 'target_currency' => $original_currency]);
        $usd_to_change = executeQuery('hotopay.getCurrency', ['base_currency' => 'USD', 'target_currency' => $change_currency]);

        if (!empty($usd_to_original->data->base_currency) && !empty($usd_to_change->data->base_currency))
        {
            $usd_base_in_original = $usd_to_original->data->base_value;
            $target_base_in_original = $usd_to_original->data->target_value;

            $original_amount_in_usd = ($amount / $target_base_in_original) * $usd_base_in_original;

            $base = $usd_to_change->data->base_value;
            $target = $usd_to_change->data->target_value;

            return round(($original_amount_in_usd / $base) * $target, $round);
        }

        return false;
    }

    public function updateCurrency()
    {
        $config = $this->getConfig();
        if ($config->hotopay_currency_renew_time + (3600 * 12) > time()) return new BaseObject(-1, 'Next renew is '.date('Y-m-d H:i:s', $config->hotopay_currency_renew_time + (3600 * 12)));

        $from = 'USD';
        $to = ['KRW','JPY','CNY','EUR','USD'];

        switch ($config->hotopay_currency_renew_api_type)
        {
            case 'fixerio':
                $driver = new \HotopayLib\Currency\driver\Fixer($config->fixer_io_api_key);
                $currency_data = $driver->getLatestCurrency($from, $to);
                break;

            case 'exchangeratehost':
                $driver = new \HotopayLib\Currency\driver\Exchangeratehost($config->exchangeratehost_api_key);
                $currency_data = $driver->getLatestCurrency($from, $to);
                break;

            case 'hotoapi':
                $driver = new \HotopayLib\Currency\driver\Hotoapi();
                $currency_data = $driver->getLatestCurrency($from, $to);
                break;

            case 'none':
            default:
                $currency_data = [];
                break;
        }
        

        foreach ($currency_data as $currency)
        {
            $currency['update_time'] = date('Y-m-d H:i:s');
            $output = executeQuery('hotopay.insertCurrency', $currency);
            if (!$output->toBool()) return $output;
        }
        
        $config->hotopay_currency_renew_time = time();
        $this->setConfig($config);
        return true;
    }

    public static function minusOptionStock(int $option_srl, int $quantity = 1)
    {
        $option = self::getOption($option_srl);

        $args = new stdClass();
        $args->option_srl = $option_srl;
        $args->stock = $option->stock - abs($quantity);
        $output = executeQuery('hotopay.updateOptionStock', $args);
        
        return $output;
    }

    public static function plusOptionStock(int $option_srl, int $quantity = 1)
    {
        $option = self::getOption($option_srl);

        $args = new stdClass();
        $args->option_srl = $option_srl;
        $args->stock = $option->stock + abs($quantity);
        $output = executeQuery('hotopay.updateOptionStock', $args);

        return $output;
    }

    public static function rollbackOptionStock(int $purchase_srl)
    {
        $items = self::getPurchaseItems($purchase_srl);
		foreach ($items as $item)
		{
			$option_srl = $item->option_srl;
			if($option_srl != 0)
			{
				self::plusOptionStock($option_srl, 1);
			}
		}
    }

    public static function setOptionStock(int $option_srl, int $quantity = 1)
    {
        $args = new stdClass();
        $args->option_srl = $option_srl;
        $args->stock = $quantity;
        $output = executeQuery('hotopay.updateOptionStock', $args);

        return $output;
    }

    public static function getProductByDocumentSrl(int $document_srl): object
    {
        $args = new stdClass();
        $args->document_srl = $document_srl;

        $output = executeQuery('hotopay.getProductSrlByDocumentSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError($output->getMessage());
        }

        return self::getProduct($output->data->product_srl ?: 0);
    }

    public static function getProductsByDocumentSrls($document_srls)
    {
        $args = new stdClass();
        $args->document_srl = $document_srls;

        $output = executeQueryArray('hotopay.getProductSrlByDocumentSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError($output->getMessage());
        }

        $product_srls = array();
        foreach($output->data as $product)
        {
            $product_srls[] = $product->product_srl;
        }

        $products = self::getProducts($product_srls);
        $returns = array();
        foreach($products as $product)
        {
            $returns[$product->document_srl] = $product;
        }

        return $returns;
    }

    /**
     * hotopay_cart 테이블에 아이템 하나를 추가한다.
     *
     * @param object $obj
     */
    public static function insertCartItem(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.insertCartItem', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_cart 테이블에서 멤버의 카트 아이템을 가져온다.
     *
     * @param int $member_srl
     */
    public static function getCartItems(int $member_srl): array
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;

        $output = executeQueryArray('hotopay.getCartItems', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: array();
    }

    /**
     * hotopay_cart 테이블에서 아이템을 삭제한다.
     *
     * @param int $cart_item_srl
     * @param int $member_srl
     */
    public static function deleteCartItem(int $cart_item_srl, int $member_srl): object
    {
        $args = new \stdClass();
        $args->cart_item_srl = $cart_item_srl;
        $args->member_srl = $member_srl;

        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.deleteCartItem', $args);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_cart 테이블에서 아이템을 업데이트한다.
     *
     * @param object $obj
     */
    public static function updateCartItem(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.updateCartItem', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    public static function getCartItemCount(int $member_srl): int
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;

        $output = executeQuery('hotopay.getCartItemCount', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data->count ?: 0;
    }

    public function getCartItemCountInCache(int $member_srl): int
    {
        $item_count = $this->getCache('cart_item_count_' . $member_srl);

        if ($item_count === false)
        {
            $item_count = self::getCartItemCount($member_srl);
            $this->setCache('cart_item_count_' . $member_srl, $item_count, 3600);
        }

        return $item_count;
    }

    public static function updateExpiredPurchaseStatus(): object
    {
        $oHotopayController = HotopayController::getInstance();

        $args = new \stdClass();
        $args->pay_status = array('WAITING_FOR_DEPOSIT', 'PENDING');
        $args->regdate = time() - 86400 * 3; // 3 days
        $output = executeQueryArray('hotopay.getExpiredPurchase', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        foreach ($output->data as $purchase_data)
        {
            $purchase_srl = $purchase_data->purchase_srl;

            $args = new stdClass();
            $args->purchase_srl = $purchase_srl;
            $args->pay_status = 'EXPIRED';
            executeQuery('hotopay.updatePurchaseStatus', $args);

            HotopayModel::rollbackOptionStock($purchase_srl);
            $oHotopayController->refundUsedPoint($purchase_srl);

            $trigger_obj = new stdClass();
            $trigger_obj->purchase_srl = $purchase_srl;
            $trigger_obj->pay_status = 'EXPIRED';
            $trigger_obj->pay_data = new stdClass();
            $trigger_obj->pay_pg = 'PG';
            $trigger_obj->amount = $purchase_data->product_purchase_price;
            ModuleHandler::triggerCall('hotopay.updatePurchaseStatus', 'after', $trigger_obj);
        }

        if (!empty($output->data)) {
            $trigger_obj = new stdClass();
            $trigger_obj->update_count = count($output->data);
            ModuleHandler::triggerCall('hotopay.updateExpiredPurchaseStatus', 'after', $trigger_obj);
        }

        return new BaseObject();
    }

    /**
     * hotopay_billing_key 테이블에 BillingKey 하나를 추가한다.
     *
     * @param object $obj
     */
    public static function insertBillingKey(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.insertBillingKey', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey를 가져온다.
     *
     * @param int $key_idx
     */
    public static function getBillingKey(int $key_idx): object
    {
        $args = new \stdClass();
        $args->key_idx = $key_idx;

        $output = executeQuery('hotopay.getBillingKey', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey를 가져온다.
     *
     * @param int $key_idx
     */
    public static function getBillingKeyByKeyHash(int $member_srl, string $key_hash): object
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;
        $args->key_hash = $key_hash;

        $output = executeQuery('hotopay.getBillingKeyByKeyHash', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    public static function getBillingKeyByKeyNumber(int $member_srl, string $key_number): object
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;
        $args->number = $key_number;

        $output = executeQuery('hotopay.getBillingKeyByKeyNumber', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey 여러 건을 가져온다.
     *
     * @param int $member_srl
     */
    public static function getBillingKeys(int $member_srl): array
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;

        $output = executeQueryArray('hotopay.getBillingKeys', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: array();
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey를 리스트 형식으로 가져온다.
     * 
     * @param object $obj
     */
    public static function getBillingKeyList(object $obj): object
    {
        $obj->sort_index = $obj->sort_index ?? 'key_idx';
        $obj->order_type = $obj->order_type ?? 'desc';
        $obj->list_count = $obj->list_count ?? 20;
        $obj->page_count = $obj->page_count ?? 10;
        $obj->page = $obj->page ?? 1;

        $output = executeQueryArray('hotopay.getBillingKeyList', $obj);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output;
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey를 업데이트한다.
     *
     * @param object $obj
     */
    public static function updateBillingKey(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.updateBillingKey', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_billing_key 테이블에서 BillingKey를 삭제한다.
     *
     * @param int $key_idx
     */
    public static function deleteBillingKey(int $key_idx): object
    {
        $args = new \stdClass();
        $args->key_idx = $key_idx;

        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.deleteBillingKey', $args);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    public static function deleteBillingKeyByKeyHash(int $member_srl, string $key_hash): object
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;
        $args->key_hash = $key_hash;

        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.deleteBillingKeyByKeyHash', $args);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_subscription 테이블에 Subscription 하나를 추가한다.
     * 
     * @param object $obj
     */
    public static function insertSubscription(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.insertSubscription', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_subscription 테이블에서 Subscription를 가져온다.
     * 
     * @param int $subscription_srl
     */
    public static function getSubscription(int $subscription_srl): object
    {
        $args = new \stdClass();
        $args->subscription_srl = $subscription_srl;

        $output = executeQuery('hotopay.getSubscription', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    /**
     * hotopay_subscription 테이블에서 SubscriptionsByMemberSrl를 가져온다.
     * 
     * @param int $member_srl
     */
    public static function getSubscriptionsByMemberSrl(int $member_srl): array
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;

        $output = executeQueryArray('hotopay.getSubscriptionsByMemberSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: [];
    }

    /**
     * hotopay_subscription 테이블에서 유저의 활성화 상태인 구독을 가져온다.
     *
     * @param int $member_srl
     * @param int $product_srl
     */
    public static function getActiveSubscriptionsByMemberSrlWithProductSrlAndStatus(int $member_srl, int $product_srl): array
    {
        $args = new \stdClass();
        $args->member_srl = $member_srl;
        $args->product_srl = $product_srl;
        $args->status = ['ACTIVE'];

        $output = executeQueryArray('hotopay.getSubscriptionsByMemberSrlWithProductSrlAndStatus', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: [];
    }

    /**
     * hotopay_subscription 테이블에서 Subscription를 리스트 형식으로 가져온다.
     * 
     * @param object $obj
     */
    public static function getSubscriptionList(object $obj): object
    {
        $obj->sort_index = $obj->sort_index ?? 'subscription_srl';
        $obj->order_type = $obj->order_type ?? 'desc';
        $obj->list_count = $obj->list_count ?? 20;
        $obj->page_count = $obj->page_count ?? 10;
        $obj->page = $obj->page ?? 1;

        $output = executeQueryArray('hotopay.getSubscriptionList', $obj);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output;
    }


    /**
     * hotopay_subscription 테이블에서 Subscription를 업데이트한다.
     * 
     * @param object $obj
     */
    public static function updateSubscription(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.updateSubscription', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_subscription 테이블에서 Subscription를 삭제한다.
     * 
     * @param int $subscription_srl
     */
    public static function deleteSubscription(int $subscription_srl): object
    {
        $args = new \stdClass();
        $args->subscription_srl = $subscription_srl;

        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.deleteSubscription', $args);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    public function encryptKey(string $key): string
    {
        $config = $this->getConfig();
        switch($config->hotopay_billingkey_encryption)
        {
            case 'none':
                return sprintf("none:%s", $key);

            case 'awskms':
                return sprintf("awskms:%s", \Rhymix\Modules\Keyenc\Models\AWSKMS::EncryptShort($config->hotopay_aws_kms_arn, $key));
        }
    }

    public function decryptKey(string $key): string
    {
        $config = $this->getConfig();

        $part = explode(':', $key);
        switch($part[0])
        {
            case 'none':
                return $part[1];

            case 'awskms':
                return \Rhymix\Modules\Keyenc\Models\AWSKMS::DecryptShort($config->hotopay_aws_kms_arn, $part[1]);
        }
    }

    public static function insertProductExtraInfo(object $obj): object
    {
        $output = executeQuery('hotopay.insertProductExtraInfo', $obj);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return new BaseObject();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfo를 업데이트한다.
     * 
     * @param object $obj
     */
    public static function updateProductExtraInfo(object $obj): object
    {
        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.updateProductExtraInfo', $obj);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfo를 삭제한다.
     * 
     * @param int $info_srl
     */
    public static function deleteProductExtraInfo(int $info_srl): object
    {
        $args = new \stdClass();
        $args->info_srl = $info_srl;

        $oDB = DB::getInstance();
        $oDB->begin();

        $output = executeQuery('hotopay.deleteProductExtraInfo', $args);
        if(!$output->toBool())
        {
            $oDB->rollback();
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }
        $oDB->commit();

        return new BaseObject();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfo를 가져온다.
     * 
     * @param array $product_srl
     */
    public static function getProductExtraInfo(array $product_srls): array
    {
        $args = new \stdClass();
        $args->product_srl = $product_srls;

        $output = executeQueryArray('hotopay.getProductExtraInfo', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: array();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfoByKeyName를 가져온다.
     * 
     * @param string $key_name
     */
    public static function getProductExtraInfoByKeyName(string $key_name): object
    {
        $args = new \stdClass();
        $args->key_name = $key_name;

        $output = executeQuery('hotopay.getProductExtraInfoByKeyName', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfoByKeyName를 가져온다.
     * 
     * @param string $key_name
     */
    public static function getProductExtraInfoByInfoSrl(string $info_srl): object
    {
        $args = new \stdClass();
        $args->info_srl = $info_srl;

        $output = executeQuery('hotopay.getProductExtraInfoByInfoSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: new \stdClass();
    }

    /**
     * hotopay_product_extra_info 테이블에서 ProductExtraInfo를 리스트 형식으로 가져온다.
     * 
     * @param object $obj
     */
    public static function getProductExtraInfoList(object $obj): object
    {
        $obj->sort_index = $obj->sort_index ?? 'product_srl';
        $obj->order_type = $obj->order_type ?? 'desc';
        $obj->list_count = $obj->list_count ?? 20;
        $obj->page_count = $obj->page_count ?? 10;
        $obj->page = $obj->page ?? 1;

        $output = executeQueryArray('hotopay.getProductExtraInfoList', $obj);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output;
    }

    /**
     * hotopay_purchase_extra_info 테이블에 PurchaseExtraInfo 하나를 추가한다.
     * 
     * @param object $obj
     */
    public static function insertPurchaseExtraInfo(object $obj): object
    {
        $output = executeQuery('hotopay.insertPurchaseExtraInfo', $obj);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return new BaseObject();
    }

    /**
     * hotopay_purchase_extra_info 테이블에서 PurchaseExtraInfo를 가져온다.
     * 
     * @param int $purchase_srl
     */
    public static function getPurchaseExtraInfo(int $purchase_srl): array
    {
        $args = new \stdClass();
        $args->purchase_srl = $purchase_srl;

        $output = executeQueryArray('hotopay.getPurchaseExtraInfo', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output->data ?: [];
    }

    public static function copyPurchaseExtraInfo(int $target_srl, int $purchase_srl): BaseObject
    {
        $origin = self::getPurchaseExtraInfo($purchase_srl);

        $oDB = DB::getInstance();
        $oDB->begin();

        foreach ($origin as $info)
        {
            unset($info->info_srl);
            $info->info_srl = getNextSequence();
            $info->purchase_srl = $target_srl;
            $info->regdate = date('Y-m-d H:i:s');
            self::insertPurchaseExtraInfo($info);
        }

        $oDB->commit();

        return new BaseObject();
    }

    public static function updatePurchaseItemSubscriptionSrl(int $item_srl, int $subscription_srl): BaseObject
    {
        $args = new \stdClass();
        $args->item_srl = $item_srl;
        $args->subscription_srl = $subscription_srl;

        $output = executeQuery('hotopay.updatePurchaseItemSubscriptionSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return new BaseObject();
    }

    public static function getCartItemList()
    {
        $args = new \stdClass();
        $args->order_type = "desc";

        $output = executeQueryArray('hotopay.getCartItemList', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", $output->getMessage(), __FILE__, __LINE__));
        }

        return $output;
    }
}
