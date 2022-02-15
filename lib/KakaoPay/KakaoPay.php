<?php

class KakaoPay extends Hotopay {
    public static $KAKAOPAY_URL = 'https://kapi.kakao.com';

    public function getAuthorizationToken()
    {
        $config = $this->getConfig();
        return $config->kakaopay_admin_key;
    }

    public function createOrder($order, $order_srl, $member_user_id = 'hotopay_user')
    {
        $config = $this->getConfig();
        $http_host = getenv('HTTP_HOST');
        $purchase_data = json_decode($order->products);

        $url = self::$KAKAOPAY_URL."/v1/payment/ready";
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            'Authorization: KakaoAK '.$this->getAuthorizationToken()
        );
        $post_data = array(
            "cid" => $config->kakaopay_cid_key,
            "cid_secret" => $config->kakaopay_cid_secret_key,
            "partner_order_id" => "HT".$order_srl,
            "partner_user_id" => $member_user_id,
            "item_name" => $purchase_data->t,
            "quantity" => 1,
            "total_amount" => $order->product_purchase_price,
            "tax_free_amount" => 0,
            "approval_url" => "https://{$http_host}/hotopay/payStatus/kakaopay/success/HT{$order_srl}",
            "cancel_url" => "https://{$http_host}/hotopay/payStatus/kakaopay/cancel/HT{$order_srl}",
            "fail_url" => "https://{$http_host}/hotopay/payStatus/kakaopay/fail/HT{$order_srl}",
        );
        
        if($config->kakaopay_install_month >= 0)
        {
            $post_data["install_month"] = $config->kakaopay_install_month;
        }

        $post_field_string = http_build_query($post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response_json);

        $response = new stdClass();
        $response->error = ($http_code == 200) ? 0 : -1;
        $response->message = ($output->msg) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }

    public function acceptOrder($purchase_srl, $pg_token, $member_user_id = 'hotopay_user')
    {
        $config = $this->getConfig();
        $oHotopayModel = getModel('hotopay');
        $purchase = $oHotopayModel->getPurchase($purchase_srl);
        $order_id = 'HT'.$purchase_srl;
        $amount = $purchase->product_purchase_price;

        $pay_data = json_decode($purchase->pay_data);

        $url = self::$KAKAOPAY_URL."/v1/payment/approve";
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            'Authorization: KakaoAK '.$this->getAuthorizationToken()
        );
        $post_field_string = http_build_query(array(
            "cid" => $config->kakaopay_cid_key,
            "cid_secret" => $config->kakaopay_cid_secret_key,
            "tid" => $pay_data->data->tid,
            "partner_order_id" => $order_id,
            "partner_user_id" => $member_user_id,
            "pg_token" => $pg_token,
            "total_amount" => $amount,
        ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response_json);

        $response = new stdClass();
        $response->error = ($http_code == 200) ? 0 : -1;
        $response->message = ($output->message) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }

    public function cancelOrder($purchase_srl, $cancel_reason, $cancel_amount)
    {
        $response = new stdClass();
        $response->error = -1;
        $response->message = '카카오페이는 아직 취소가 구현되지 않았습니다.';
        $response->http_code = 400;
        $response->data = array();

        return $response;
    }
}