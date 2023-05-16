<?php

class Toss extends Hotopay {
    public static $TOSS_URL = 'https://api.tosspayments.com';

    public function getAccessToken()
    {
        $config = $this->getConfig();
        return base64_encode("$config->toss_payments_secret_key:");
    }

    public function acceptOrder($purchase_srl, $payment_key)
    {
        $oHotopayModel = getModel('hotopay');
        $purchase = $oHotopayModel->getPurchase($purchase_srl);
        $amount = $purchase->product_purchase_price;
        $order_id = 'HT'.str_pad($purchase_srl, 4, "0", STR_PAD_LEFT);

        $url = self::$TOSS_URL."/v1/payments/{$payment_key}";
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.$this->getAccessToken()
        );
        $post_field_string = json_encode(array(
            "orderId" => $order_id,
            "amount" => $amount
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

    public function cancelOrder($purchase_srl, $cancel_reason, $cancel_amount = -1, $bank_info = array())
    {
        $oHotopayModel = getModel('hotopay');
        $purchase = $oHotopayModel->getPurchase($purchase_srl);
        $pay_data_list = json_decode($purchase->pay_data);

        if ($purchase->is_billing != 'Y')
        {
            $pay_data_list = array($pay_data_list);
        }

        $post_field = array(
            "cancelReason" => $cancel_reason
        );

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

        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.$this->getAccessToken()
        );

        $outputList = array();
        $http_code = 0;
        $output = new stdClass();
        foreach ($pay_data_list as $pay_data)
        {
            $payment_key = $pay_data->paymentKey;
            $amount = $purchase->product_purchase_price;

            if($cancel_amount > 0 && $cancel_amount < $amount)
            {
                // 부분 환불
                $post_field["cancelAmount"] = $cancel_amount;
            }

            if ($purchase->is_billing == 'Y')
            {
                // 전체 취소
                $post_field["cancelAmount"] = $pay_data->totalAmount;
            }

            $url = self::$TOSS_URL."/v1/payments/{$payment_key}/cancel";
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
            $outputList[] = $output;
        }

        $response = new stdClass();
        $response->error = ($http_code == 200) ? 0 : -1;
        $response->message = ($output->message) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $outputList;
        
        return $response;
    }

    public function requestBillingKey(string $customerKey, string $authKey): BaseObject
    {
        $url = self::$TOSS_URL."/v1/billing/authorizations/issue";
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.$this->getAccessToken()
        );
        $post_field_string = json_encode(array(
            'authKey' => $authKey,
            'customerKey' => $customerKey
        ));

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

        $response = new BaseObject();
        $response->error = ($http_code == 200) ? 0 : -1;
        $response->message = ($output->message) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }

    public function requestBilling(object $subscription): BaseObject
    {
        $oHotopayModel = HotopayModel::getInstance();
        $billingKeyObject = HotopayModel::getBillingKey($subscription->billing_key_idx);
        $billingKey = $oHotopayModel->decryptKey($billingKeyObject->key);
        $customerKey = "HTMEMBER".$subscription->member_srl;
        $purchase_srl = getNextSequence();
        $orderId = "HT".str_pad($purchase_srl, 4, "0", STR_PAD_LEFT);
        $member = MemberModel::getMemberInfoByMemberSrl($subscription->member_srl);

        $url = self::$TOSS_URL."/v1/billing/".$billingKey;
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.$this->getAccessToken()
        );
        $post_field_string = json_encode(array(
            'customerKey' => $customerKey,
            'amount' => $subscription->price,
            'orderId' => $orderId,
            'orderName' => $subscription->item_name,
            'customerEmail' => $member->email,
            'customerName' => $member->user_name,
            'taxFreeAmount' => 0,
        ));

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
        $output->purchase_srl = $purchase_srl;

        $response = new BaseObject();
        $response->error = ($http_code == 200) ? 0 : -1;
        $response->message = ($output->message) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }
}