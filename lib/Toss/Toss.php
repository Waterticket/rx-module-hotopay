<?php

class Toss extends Hotopay {
    public static $TOSS_URL = 'https://api.tosspayments.com';

    public function getAccessToken()
    {
        $config = $this->getConfig();
        return base64_encode("$config->toss_payments_secret_key:");
    }

    public function cancelOrder($purchase_srl, $cancel_reason, $cancel_amount = -1, $bank_info = array())
    {
        $oHotopayModel = getModel('hotopay');
        $purchase = $oHotopayModel->getPurchase($purchase_srl);
        $pay_data = json_decode($purchase->pay_data);
        $payment_key = $pay_data->paymentKey;
        $amount = $purchase->product_purchase_price;

        $post_field = array(
            "cancelReason" => $cancel_reason
        );

        if($cancel_amount > 0 && $cancel_amount < $amount)
        {
            // 부분 환불
            $post_field["cancelAmount"] = $cancel_amount;
        }

        if($purchase->pay_method == 'v_account')
        {
            // 가상계좌는 환불 계좌 필수
            if(empty($bank_info)) return $this->createObject(-1, "환불 계좌를 입력해주세요.");

            $post_field["refundReceiveAccount"] = array(
                "bank" => $bank_info["bank"],
                "accountNumber" => $bank_info["accountNumber"],
                "holderName" => $bank_info["holderName"],
            );
        }

        $url = self::$TOSS_URL."/v1/payments/{$payment_key}/cancel";
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.$this->getAccessToken()
        );
        $post_field_string = json_encode($post_field);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response);

        $response = new stdClass();
        $response->error = 0;
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }
}