<?php

class Paypal extends Hotopay {
    public static $PAYPAL_URL = 'https://api-m.paypal.com';

    private static $AccessToken = '';
    private static $AccessToken_Expires = 0;

    public function getAccessToken()
    {
        if(self::$AccessToken_Expires == 0)
        {
            if($this->getCache('Paypal_AccessToken'))
            {
                self::$AccessToken = $this->getCache('Paypal_AccessToken');
                self::$AccessToken_Expires = $this->getCache('Paypal_AccessToken_Expires');
            }
        }

        if(self::$AccessToken_Expires < time()) // 토큰 만료됨
        {
            $this->_RequestAccessToken();
        }

        return self::$AccessToken;
    }

    public function clearAccessToken()
    {
        $this->deleteCache('Paypal_AccessToken');
        $this->deleteCache('Paypal_AccessToken_Expires');

        self::$AccessToken = '';
        self::$AccessToken_Expires = '';
    }

    private function _RequestAccessToken()
    {
        $config = $this->getConfig();
        $url = self::$PAYPAL_URL.'/v1/oauth2/token';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_USERPWD, $config->paypal_client_key . ':' . $config->paypal_secret_key);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Accept-Language: en_US';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            debugPrint('Error:' . curl_error($ch));
        }

        if($http_code != 200)
        {
            debugPrint('Error: Http_code: '.$http_code);
        }
        curl_close($ch);

        $result_data = json_decode($result);
        debugPrint($result_data);

        self::$AccessToken = $result_data->access_token;
        self::$AccessToken_Expires = time() + $result_data->expires_in - 300; // 5분정도 만료를 앞당김

        $this->setCache('Paypal_AccessToken', self::$AccessToken, self::$AccessToken_Expires - time());
        $this->setCache('Paypal_AccessToken_Expires', self::$AccessToken_Expires, self::$AccessToken_Expires - time());
    }

    public function createOrder($order, $order_srl)
    {
        $order_id = 'HT'.str_pad($order_srl, 4, "0", STR_PAD_LEFT);
        $accessToken = $this->getAccessToken();
        $http_host = getenv('HTTP_HOST');
        $post_field = array(
            "intent" => "CAPTURE",
            "application_context" => array(
                "return_url" => "https://{$http_host}/hotopay/payStatus/paypal/success/{$order_id}",
                "cancel_url" => "https://{$http_host}/hotopay/payStatus/paypal/fail/{$order_id}",
                "brand_name" => "HotoPay",
                "locale" => "ko-KR",
                "landing_page" => "LOGIN",
                "user_action" => "PAY_NOW"
            ),
            "purchase_units" => array(
                array(
                    "amount" => array(
                        "currency_code" => $order->purchase->currency_code,
                        "value" => $order->purchase->total,
                        "breakdown" => array("item_total"=>array("value" => $order->purchase->total, "currency_code" => $order->purchase->currency_code))
                    ),
                    "items" => array()
                )
            )
        );

        foreach($order->items as $item)
        {
            $temp_item = new stdClass();
            $temp_item->name = $item->name;
            $temp_item->description = $item->description;
            $temp_item->quantity = $item->count;
            $temp_item->unit_amount = array("value" => $item->value, "currency_code" => $order->purchase->currency_code);

            array_push($post_field['purchase_units'][0]['items'], $temp_item);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::$PAYPAL_URL.'/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_field));

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        $result_data = json_decode($result);
        
        $pay_object = new stdClass();
        $pay_object->id = $result_data->id;
        $pay_object->status = $result_data->status;
        
        foreach($result_data->links as $link)
        {
            $pay_object->links->{$link->rel} = $link;
        }

        return $pay_object;
    }

    public function getOrderDetails($id)
    {
        $accessToken = $this->getAccessToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::$PAYPAL_URL.'/v2/checkout/orders/'.$id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($result);
    }

    public function authorizeOrder($id)
    {
        $accessToken = $this->getAccessToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::$PAYPAL_URL.'/v2/checkout/orders/'.$id.'/authorize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
    }

    public function captureOrder($id)
    {
        $accessToken = $this->getAccessToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::$PAYPAL_URL.'/v2/checkout/orders/'.$id.'/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($result);
    }

    public function cancelOrder($order_srl, $cancel_reason = '', $cancel_amount = 0)
    {
        $oHotopayModel = getModel('hotopay');
        $purchase = $oHotopayModel->getPurchase($order_srl);
        $order_id = 'HT'.str_pad($order_srl, 4, "0", STR_PAD_LEFT);
        $pay_data = json_decode($purchase->pay_data);
        $refund_link = '';
        
        foreach($pay_data->purchase_units[0]->payments->captures[0]->links as $link)
        {
            if($link->rel == "refund")
            {
                $refund_link = $link->href;
                break;
            }
        }

        if($refund_link == '')
            return $this->createObject(-1, 'Paypal 환불 링크를 가져올 수 없습니다.');

        $post_field = array(
            "invoice_number" => $order_id,
        );

        if($cancel_reason != '')
        {
            $post_field["reason"] = $cancel_reason;
        }

        if($cancel_amount != 0)
        {
            $post_field["amount"] = array(
                "value" => $cancel_amount,
                "currency_code" => "USD"
            );
        }

        $accessToken = $this->getAccessToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $refund_link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_field));

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        $response_data = json_decode($result);

        $response = new stdClass();
        $response->error = ($http_code == 201) ? 0 : -1;
        $response->message = ($http_code == 201) ? 'success' : '환불을 실패하였습니다. 데이터를 확인해주세요.';
        $response->data = $response_data;

        return $response;
    }
}