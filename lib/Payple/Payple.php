<?php

class Payple extends Hotopay {
    public static $PAYPLE_URL = 'https://cpay.payple.kr'; // 서비스 도메인

    public function __construct()
    {
        $config = $this->getConfig();
        if ($config->payple_server == 'demo')
        {
            self::$PAYPLE_URL = 'https://democpay.payple.kr'; // 테스트용 도메인
        }
    }

    public function getPartnerAuth($PCD_PAYCANCEL_FLAG = false, $PCD_PAY_TYPE = '')
    {
        $config = $this->getConfig();
        $http_host = $_SERVER['HTTP_HOST'];

        if (empty($config->payple_referer_domain))
        {
            $config->payple_referer_domain = $http_host;
        }

        if ($http_host != $config->payple_referer_domain)
        {
            throw new Exception('Referer domain is not matched.');
        }

        $url = self::$PAYPLE_URL."/php/auth.php";
        $headers = array(
            'Content-Type: application/json;charset=utf-8',
            'Cache-Control: no-cache',
            'Referer: https://'.$config->payple_referer_domain,
        );
        $post_data = array(
            "cst_id" => $config->payple_cst_id,
            "custKey" => $config->payple_cust_key,
        );

        if ($PCD_PAYCANCEL_FLAG)
        {
            $post_data['PCD_PAYCANCEL_FLAG'] = 'Y';
        }

        if (!empty($PCD_PAY_TYPE))
        {
            $post_data['PCD_PAY_TYPE'] = $PCD_PAY_TYPE;
            $post_data['PCD_SIMPLE_FLAG'] = 'Y';
        }

        $post_field_string = json_encode($post_data);

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
        $response->error = ($http_code == 200 && $output->result == 'success') ? 0 : -1;
        $response->message = ($output->result_msg) ?? 'success';
        $response->http_code = $http_code;
        $response->data = $output;

        return $response;
    }

    public function confirmPaywork($vars, $purchase, $PCD_PAYER_ID)
    {
        $config = $this->getConfig();
        $http_host = $_SERVER['HTTP_HOST'];

        if (empty($config->payple_referer_domain))
        {
            $config->payple_referer_domain = $http_host;
        }

        if ($http_host != $config->payple_referer_domain)
        {
            return new BaseObject(-1, 'Referer domain is not matched.');
        }

        if (empty($vars->PCD_AUTH_KEY) || empty($vars->PCD_PAY_REQKEY) || empty($PCD_PAYER_ID))
        {
            return new BaseObject(-1, 'missing required parameters.');
        }

        $url = self::$PAYPLE_URL."/php/PayCardConfirmAct.php?ACT_=PAYM";
        $headers = array(
            'Content-Type: application/json;charset=utf-8',
            'Cache-Control: no-cache',
            'Referer: https://'.$config->payple_referer_domain,
        );
        $post_data = array(
            "PCD_CST_ID" => $config->payple_cst_id,
            "PCD_CUST_KEY" => $config->payple_cust_key,
            "PCD_AUTH_KEY" => $vars->PCD_AUTH_KEY,
            "PCD_PAY_REQKEY" => $vars->PCD_PAY_REQKEY,
            "PCD_PAYER_ID" => $PCD_PAYER_ID,
        );

        $post_field_string = json_encode($post_data);

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

        if ($output->PCD_PAY_RST != "success" || !str_contains($output->PCD_PAY_CODE, "0000"))
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG);
        }

        if ($output->PCD_PAY_TOTAL != $vars->PCD_PAY_TOTAL)
        {
            return new BaseObject(-1, '결제금액이 일치하지 않습니다. ERR1');
        }

        if ($output->PCD_PAY_TOTAL != $purchase->product_purchase_price)
        {
            return new BaseObject(-1, '결제금액이 일치하지 않습니다. ERR2');
        }

        $output_obj = new BaseObject();
        $output_obj->data = $output;
        return $output_obj;
    }

    public function cancelOrder($purchase_srl, $cancel_reason, $cancel_amount = -1)
    {
        $config = $this->getConfig();
        $http_host = $_SERVER['HTTP_HOST'];
        $order_id = 'HT'.str_pad($purchase_srl, 4, "0", STR_PAD_LEFT);

        if (empty($config->payple_referer_domain))
        {
            $config->payple_referer_domain = $http_host;
        }

        if ($http_host != $config->payple_referer_domain)
        {
            return new BaseObject(-1, 'Referer domain is not matched.');
        }

        $oHotopayModel = HotopayModel::getInstance();
        $purchase = $oHotopayModel->getPurchase($purchase_srl);
        if ($cancel_amount == -1)
        {
            $cancel_amount = $purchase->product_purchase_price;
        }

        $partner_auth = $this->getPartnerAuth(true);
        if ($partner_auth->error != 0)
        {
            return new BaseObject(-1, $partner_auth->message);
        }

        $partner_auth = $partner_auth->data;
        $cst_id = $partner_auth->cst_id;
        $cust_key = $partner_auth->custKey;
        $auth_key = $partner_auth->AuthKey;
        $PCD_PAY_URL = $partner_auth->PCD_PAY_URL;

        $url = self::$PAYPLE_URL.$PCD_PAY_URL;
        $headers = array(
            'Content-Type: application/json;charset=utf-8',
            'Cache-Control: no-cache',
            'Referer: https://'.$config->payple_referer_domain,
        );
        $post_data = array(
            "PCD_CST_ID" => $cst_id,
            "PCD_CUST_KEY" => $cust_key,
            "PCD_AUTH_KEY" => $auth_key,
            "PCD_REFUND_KEY" => $config->payple_refund_key,
            "PCD_PAYCANCEL_FLAG" => 'Y',
            "PCD_PAY_OID" => $order_id,
            "PCD_PAY_DATE" => intval(date("Ymd", $purchase->regdate)),
            "PCD_REFUND_TOTAL" => $cancel_amount,
        );

        $post_field_string = json_encode($post_data);

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
        $is_success = $output->PCD_PAY_RST == "success" && str_contains($output->PCD_PAY_CODE, "0000");
        if(!$is_success)
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG);
        }

        $cancel_obj = new stdClass();
        $cancel_obj->error = $is_success ? 0 : -1;
        $cancel_obj->message = $output->PCD_PAY_MSG;
        $cancel_obj->data = $output;

        return $cancel_obj;
    }

    public function requestBilling(object $subscription): object
    {
        $config = $this->getConfig();
        $auth_data = $this->getPartnerAuth(false, $subscription->payment_type);
        if ($auth_data->error != 0)
        {
            return new BaseObject(-1, $auth_data->message);
        }

        $oHotopayModel = HotopayModel::getInstance();
        $auth_data = $auth_data->data;
        $cst_id = $auth_data->cst_id;
        $custKey = $auth_data->custKey;
        $AuthKey = $auth_data->AuthKey;
        $PCD_PAY_URL = $auth_data->PCD_PAY_URL;
        $purchase_srl = getNextSequence();

        $url = self::$PAYPLE_URL.$PCD_PAY_URL;
        $headers = array(
            'Content-Type: application/json;charset=utf-8',
            'Cache-Control: no-cache',
            'Referer: https://'.$config->payple_referer_domain,
        );
        $post_data = array(
            "PCD_CST_ID" => $cst_id,
            "PCD_CUST_KEY" => $custKey,
            "PCD_AUTH_KEY" => $AuthKey,
            "PCD_PAY_TYPE" => $subscription->payment_type,
            "PCD_PAYER_ID" => $oHotopayModel->decryptKey($subscription->billing_key), // 빌링키
            "PCD_PAY_GOODS" => $subscription->item_name,
            "PCD_SIMPLE_FLAG" => 'Y',
            "PCD_PAY_TOTAL" => $subscription->price,
            "PCD_PAY_OID" => "HT".str_pad($purchase_srl, 4, "0", STR_PAD_LEFT),
            "PCD_PAYER_NO" => $subscription->member_srl,
            "PCD_PAYER_NAME" => $subscription->nick_name,
            "PCD_PAYER_HP" => $subscription->phone_number,
            "PCD_PAYER_EMAIL" => $subscription->email_address,
        );

        $post_field_string = json_encode($post_data);

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
        $is_success = $output->PCD_PAY_RST == "success" && str_contains($output->PCD_PAY_CODE, "0000");
        if(!$is_success)
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG);
        }

        $output->purchase_srl = $purchase_srl;

        $billing_obj = new stdClass();
        $billing_obj->error = $is_success ? 0 : -1;
        $billing_obj->message = $output->PCD_PAY_MSG;
        $billing_obj->data = $output;

        return $billing_obj;
    }
}