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
        $args = new stdClass();
        $args->product_srl = $product_srl;
        $output = executeQuery('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        $output->data->product_option = $this->getProductOptions($product_srl);
        $output->data->extra_vars = unserialize($output->data->extra_vars);

        return $output->data;
    }

    public function getProducts($product_srl)
    {
        $args = new stdClass();
        $args->product_srl = $product_srl;
        $output = executeQueryArray('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        foreach($output->data as &$val)
        {
            $val->product_option = $this->getProductOptions($val->product_srl);
            $val->extra_vars = unserialize($val->extra_vars);
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
            return $this->createObject(-1, "?????? ???????????? ???????????? ????????????.");
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
            return $this->createObject(-1, "?????? ???????????? ???????????? ????????????.");
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
            return $this->createObject(-1, "?????? ???????????? ???????????? ????????????.");
        }

        $option_data = [];
        foreach ($output->data as $val)
        {
            $val->extra_vars = unserialize($val->extra_vars);
            $option_data[$val->product_srl] = $val;
        }

        return $option_data;
    }

    public function payStatusCodeToString($code)
    {
        switch($code)
        {
            case 'PENDING':
                return "?????? ?????????";
                break;
            
            case 'WAITING_FOR_DEPOSIT':
                return "?????? ?????????";
                break;

            case 'DONE':
                return "?????? ??????";
                break;

            case "CANCELED":
                return "?????? ??????";
                break;

            case "FAILED":
                return "?????? ??????";
                break;

            case "REFUNDED":
                return "?????????";
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
                return "????????? ??????";

            case "ts_account":
                return "????????????";

            case "v_account":
                return "????????????";

            case "card":
                return "????????????";

            case "voucher":
                return "???????????????";

            case "cellphone":
                return "?????????";

            case "paypal":
                return "?????????";

            case "kakaopay":
                return "???????????????";

            case "gpay":
                return "?????? ??????";

            case "toss":
                return "?????????";

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

        $string = str_replace("[????????????]", $config->shop_name, $string);
        $string = str_replace("[?????????]", mb_substr($purchase_data->t, 0, 50), $string);
        $string = str_replace("[??????????????????]", '<a href="'.mb_substr(Context::getDefaultUrl(),0,-1).getUrl("","mid","hotopay","act","dispHotopayOrderList").'" target="_blank" title="?????? ????????????">[?????? ????????????]</a>', $string);
        $string = str_replace("[????????????]", $account, $string);
        $string = str_replace("[????????????]", number_format($purchase->product_purchase_price), $string);
        $string = str_replace("[????????????]", 'HT'.$purchase->purchase_srl, $string);

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
}
