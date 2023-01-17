<?php

class Payple extends Hotopay {
    // public static $PAYPLE_URL = 'https://democpay.payple.kr'; // 테스트용 도메인
    public static $PAYPLE_URL = 'https://cpay.payple.kr'; // 서비스 도메인

    public function getPartnerAuth()
    {
        $config = $this->getConfig();
        $http_host = getenv('HTTP_HOST');

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

    public function confirmPaywork($vars, $purchase)
    {
        $config = $this->getConfig();
        $http_host = getenv('HTTP_HOST');

        if (empty($config->payple_referer_domain))
        {
            $config->payple_referer_domain = $http_host;
        }

        if ($http_host != $config->payple_referer_domain)
        {
            return new BaseObject(-1, 'Referer domain is not matched.');
        }

        if (empty($vars->PCD_AUTH_KEY) || empty($vars->PCD_PAY_REQKEY) || empty($vars->PCD_PAYER_ID))
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
            "PCD_PAYER_ID" => $vars->PCD_PAYER_ID,
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

        if ($output->PCD_PAY_RST != "success" || $output->PCD_PAY_RST_CODE != "0000")
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

        return new BaseObject();
    }
}