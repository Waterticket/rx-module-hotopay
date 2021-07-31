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
                return "취소됨";
                break;

            default:
                return $code;
                break;
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
}
