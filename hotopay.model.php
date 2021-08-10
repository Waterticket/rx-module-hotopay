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
	public function getProductOptions($product_srl)
    {
        $args = new stdClass();
        $args->product_srl = $product_srl;
        $output = executeQuery('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        $product = $output->data;

        $p_opt = preg_split("/\r\n|\n|\r/", $product->product_option);
        $f_opt = array();
        foreach($p_opt as $_opt){
            $_opt = mb_substr($_opt, 1, -1);
            array_push($f_opt, explode('/' , $_opt));
        }

        return $f_opt;
    }

    public function getProduct($product_srl)
    {
        $args = new stdClass();
        $args->product_srl = $product_srl;
        $output = executeQuery('hotopay.getProducts', $args);

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

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

        return $output->data;
    }

    public function getProductsAll()
    {
        $output = executeQuery('hotopay.getProductsAll');

        if(!$output->toBool() || empty($output->data))
        {
            return $this->createObject(-1, "Product does not exist.");
        }

        return $output->data;
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

    public function payStatusCodeToString($code)
    {
        switch($code)
        {
            case 'PENDING':
                return "결제 대기중";
                break;
            
            case 'WAITING_FOR_DEPOSIT':
                return "결제 대기중";
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

            case "gpay":
                return "구글 페이";

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
        
        if($purchase_data->pay_method == 'n_account')
        {
            $n_account_arr = preg_split('/\r\n|\r|\n/', $config->n_account_string);
            $account = $n_account_arr[0];
        }

        $string = str_replace("[쇼핑몰명]", $config->shop_name, $string);
        $string = str_replace("[상품명]", mb_substr($purchase_data->t, 0, 10), $string);
        $string = str_replace("[주문확인링크]", '<a href="'.getUrl("","mid","hotopay","act","dispHotopayOrderList").'" target="_blank" title="주문 확인하기">[주문 확인하기]</a>', $string);
        $string = str_replace("[계좌번호]", $account, $string);
        $string = str_replace("[주문금액]", number_format($purchase->product_purchase_price), $string);

        return $string;
    }
}
