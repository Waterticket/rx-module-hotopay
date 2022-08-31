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
    public function getProduct($product_srl)
    {
		$cache_key = 'hotopay:product:' . $product_srl;
		$product = Rhymix\Framework\Cache::get($cache_key);
        if($product) return $product;

        $args = new stdClass();
        $args->product_srl = $product_srl;
        $output = executeQuery('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        $output->data->product_option = $this->getProductOptions($product_srl);
        $output->data->extra_vars = unserialize($output->data->extra_vars);

        Rhymix\Framework\Cache::set($cache_key, $output->data);
        return $output->data;
    }

    public function getProducts($product_srls)
    {
        $args = new stdClass();
        $args->product_srl = $product_srls;
        $output = executeQueryArray('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        foreach($output->data as &$val)
        {
            $cache_key = 'hotopay:product:' . $val->product_srl;
            $cache = Rhymix\Framework\Cache::get($cache_key);
            if($cache)
            {
                $val = $cache;
                continue;
            }

            $val->product_option = $this->getProductOptions($val->product_srl);
            $val->extra_vars = unserialize($val->extra_vars);
            Rhymix\Framework\Cache::set($cache_key, $val);
        }

        return $output->data;
    }

    public function getProductsAll()
    {
        $output = executeQueryArray('hotopay.getProductsAll');

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        return $output->data;
    }
    
	public function getProductOptions($product_srl)
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

    public function getPurchase($purchase_srl)
    {
        $args = new stdClass();
        $args->purchase_srl = $purchase_srl;
        $purchase = executeQuery('hotopay.getPurchase', $args);
        if(!$purchase->toBool())
        {
            return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        return $purchase->data;
    }

    public function getPurchaseItems($purchase_srl)
    {
        $args = new stdClass();
        $args->purchase_srl = $purchase_srl;
        $output = executeQueryArray('hotopay.getPurchaseItem', $args);
        if(!$output->toBool())
        {
            return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        return $output->data;
    }

    public function getProductsByPurchaseSrl($purchase_srl)
    {
        $items = $this->getPurchaseItems($purchase_srl);
        $products = array();
        foreach($items as $item)
        {
            $products[] = $item->product_srl;
        }

        return $this->getProducts($products);
    }

    public function getOptionsByPurchaseSrl($purchase_srl)
    {
        $items = $this->getPurchaseItems($purchase_srl);
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
            return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        $option_data = [];
        foreach ($output->data as $val)
        {
            $val->extra_vars = unserialize($val->extra_vars);
            $option_data[$val->product_srl] = $val;
        }

        return $option_data;
    }

    public function getOption(int $option_srl): object
    {
        $args = new stdClass();
        $args->option_srl = $option_srl;
        $output = executeQuery('hotopay.getOptions', $args);
        if(!$output->toBool())
        {
            return $this->createObject(-1, "결제 데이터가 존재하지 않습니다.");
        }

        $output->data->extra_vars = unserialize($output->data->extra_vars);

        return $output->data;
    }

    public function payStatusCodeToString($code)
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

    public function purchaseMethodToString($pay_method)
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

            default:
                return $pay_method;
        }
    }

    public function stringCut($str,$length)
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
        $purchase_data = json_decode($purchase->products);
        $pay_data = json_decode($purchase->pay_data);
        $account = '';

        if(isset($pay_data->virtualAccount->accountNumber))
            $account = $pay_data->virtualAccount->bank.' '.$pay_data->virtualAccount->accountNumber;
        
        if($purchase->pay_method == 'n_account')
        {
            $n_account_arr = preg_split('/\r\n|\r|\n/', $config->n_account_string);
            $account = $n_account_arr[0];
        }

        $string = str_replace("[쇼핑몰명]", $config->shop_name, $string);
        $string = str_replace("[상품명]", mb_substr($purchase_data->t, 0, 50), $string);
        $string = str_replace("[주문확인링크]", '<a href="'.mb_substr(Context::getDefaultUrl(),0,-1).getUrl("","mid","hotopay","act","dispHotopayOrderList").'" target="_blank" title="주문 확인하기">[주문 확인하기]</a>', $string);
        $string = str_replace("[계좌번호]", $account, $string);
        $string = str_replace("[주문금액]", number_format($purchase->product_purchase_price), $string);
        $string = str_replace("[주문번호]", 'HT'.$purchase->purchase_srl, $string);

        return $string;
    }

    public function changeCurrency($original_currency, $change_currency, $amount)
    {
        if($original_currency == 'KRW')
        {
            switch($change_currency)
            {
                case 'USD':
                    return round($amount/1000, 2);
                    break;
            }
        }

        if($original_currency == 'USD')
        {
            switch($change_currency)
            {
                case 'KRW':
                    return $amount*1000;
                    break;
            }
        }
    }

    public function minusOptionStock(int $option_srl, int $quantity = 1)
    {
        $option = $this->getOption($option_srl);

        $args = new stdClass();
        $args->option_srl = $option_srl;
        $args->stock = $option->stock - abs($quantity);
        $output = executeQuery('hotopay.updateOptionStock', $args);
    }

    public function plusOptionStock(int $option_srl, int $quantity = 1)
    {
        $option = $this->getOption($option_srl);

        $args = new stdClass();
        $args->option_srl = $option_srl;
        $args->stock = $option->stock + abs($quantity);
        $output = executeQuery('hotopay.updateOptionStock', $args);
    }

    public function getProductByDocumentSrl(int $document_srl): object
    {
        $args = new stdClass();
        $args->document_srl = $document_srl;

        $output = executeQuery('hotopay.getProductSrlByDocumentSrl', $args);
        if(!$output->toBool())
        {
            throw new \Rhymix\Framework\Exceptions\DBError($output->getMessage());
        }

        return $this->getProduct($output->data->product_srl ?: 0);
    }

    public function getProductsByDocumentSrls($document_srls)
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

        $products = $this->getProducts($product_srls);
        $returns = array();
        foreach($products as $product)
        {
            $returns[$product->document_srl] = $product;
        }

        return $returns;
    }
}
