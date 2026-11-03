<?php

class Payple extends Hotopay {
    public static $PAYPLE_URL = 'https://cpay.payple.kr'; // 서비스 도메인

    public function __construct()
    {
        $config = $this->getConfig();
        self::$PAYPLE_URL = 'https://cpay.payple.kr';
        if ($config->payple_server == 'demo')
        {
            self::$PAYPLE_URL = 'https://democpay.payple.kr'; // 테스트용 도메인
        }
    }

    /** Webhook fields are hints only; they are never proof of payment or ownership. */
    protected function requestJson(string $path, array $data): object
    {
        $config = $this->getConfig();
        $domain = $config->payple_referer_domain ?? '';
        if (!$domain || preg_match('/[\\r\\n]/', $domain))
        {
            throw new RuntimeException('Payple Referer domain must be configured.');
        }
        $ch = curl_init(self::$PAYPLE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cache-Control: no-cache', 'Referer: https://' . $domain],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = is_string($body) ? json_decode($body) : null;
        if ($status !== 200 || !is_object($result))
        {
            throw new RuntimeException('Payple verification unavailable.');
        }
        return $result;
    }

    /** PAY_DATE list responses include current state and remaining balance (single lookup does not). */
    public function getVerifiedRefund(object $purchase): object
    {
        $config = $this->getConfig();
        $saved = json_decode($purchase->pay_data ?? '');
        // Older versions may have saved an array of payment responses.
        if (is_array($saved)) $saved = end($saved);
        $time = preg_replace('/[^0-9]/', '', $saved->PCD_PAY_TIME ?? '');
        if (strlen($time) < 8)
        {
            throw new RuntimeException('Stored Payple approval date is missing.');
        }
        $date = substr($time, 0, 8);
        if (!checkdate((int) substr($date, 4, 2), (int) substr($date, 6, 2), (int) substr($date, 0, 4)))
        {
            throw new RuntimeException('Invalid Payple approval date.');
        }
        $auth = $this->requestJson('/php/auth.php', [
            'cst_id' => $config->payple_cst_id,
            'custKey' => $config->payple_cust_key,
            'PCD_PAYCHK_LIST_FLAG' => 'Y',
        ]);
        if (($auth->result ?? '') !== 'success' || empty($auth->cst_id) || empty($auth->custKey) || empty($auth->AuthKey))
        {
            throw new RuntimeException('Payple verification authentication failed.');
        }
        $request = [
            'PCD_CST_ID' => $auth->cst_id, 'PCD_CUST_KEY' => $auth->custKey,
            'PCD_AUTH_KEY' => $auth->AuthKey, 'PCD_PAYCHK_LIST_FLAG' => 'Y',
            'PCD_START_DATE' => $date, 'PCD_END_DATE' => $date,
            'PCD_PAY_TYPE' => substr($purchase->pay_method, 6),
            'PCD_DATE_TYPE' => 'PAY_DATE', 'PCD_LIMIT' => 1000,
        ];
        $oid = 'HT'.str_pad($purchase->purchase_srl, 4, '0', STR_PAD_LEFT);
        $seen = [];
        for ($page = 0; $page < 20; $page++)
        {
            $result = $this->requestJson('/php/PayChkActList.php', $request);
            if (($result->PCD_PAY_RST ?? 'success') !== 'success' || ($result->PCD_PAY_CODE ?? '') !== 'PCHK0000' || !is_array($result->PCD_DATA->PCD_CONTENT ?? null))
            {
                throw new RuntimeException('Invalid Payple verification response.');
            }
            foreach ($result->PCD_DATA->PCD_CONTENT as $payment)
            {
                if (($payment->PCD_PAY_OID ?? '') !== $oid) continue;
                if ((string) ($payment->PCD_CST_ID ?? '') !== (string) $config->payple_cst_id
                    || (string) ($payment->PCD_PAYER_NO ?? '') !== (string) $purchase->member_srl
                    || ($payment->PCD_PAY_TYPE ?? '') !== $request['PCD_PAY_TYPE']
                    || !self::sameAmount($payment->PCD_PAY_AMOUNT ?? null, $purchase->product_purchase_price)
                    || !self::sameAmount($payment->PCD_PAY_TOTAL ?? null, 0)
                    || ($payment->PCD_PAY_STATE ?? '') !== '승인취소완료')
                {
                    throw new RuntimeException('Payple full refund was not verified.');
                }
                return $payment;
            }
            if (($result->PCD_DATA->PCD_HAS_MORE ?? null) === false) break;
            $last = $result->PCD_DATA->PCD_LASTKEY ?? null;
            if (!is_string($last) || $last === '' || isset($seen[$last])) break;
            $seen[$last] = true;
            $request['PCD_LASTKEY'] = $last;
        }
        throw new RuntimeException('Payple transaction not found within verification limit.');
    }

    public static function sameAmount($actual, $expected): bool
    {
        return (is_int($actual) || is_string($actual)) && preg_match('/^[0-9]{1,10}$/D', (string) $actual)
            && (string) (int) $actual === (string) (int) $expected;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response_json);

        $response = new stdClass();
        $response->error = ($http_code == 200 && ($output->result ?? '') == 'success') ? 0 : -1;
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

        $auth = json_decode($purchase->pay_data ?? '');
        if (!self::sameAmount($vars->PCD_PAY_TOTAL ?? null, $purchase->product_purchase_price)
            || ($vars->PCD_PAY_OID ?? '') !== 'HT'.str_pad($purchase->purchase_srl, 4, '0', STR_PAD_LEFT)
            || !is_string($auth->AuthKey ?? null) || !is_string($vars->PCD_AUTH_KEY)
            || !hash_equals($auth->AuthKey, $vars->PCD_AUTH_KEY))
        {
            return new BaseObject(-1, '결제 인증 정보가 주문과 일치하지 않습니다.');
        }
        $path = $purchase->pay_method === 'paypl_transfer' ? '/php/PayConfirmAct.php?ACT_=PAYM' : '/php/PayCardConfirmAct.php?ACT_=PAYM';
        $post_data = array(
            "PCD_CST_ID" => $config->payple_cst_id,
            "PCD_CUST_KEY" => $config->payple_cust_key,
            "PCD_AUTH_KEY" => $vars->PCD_AUTH_KEY,
            "PCD_PAY_REQKEY" => $vars->PCD_PAY_REQKEY,
            "PCD_PAYER_ID" => $PCD_PAYER_ID,
        );

        try
        {
            $output = $this->requestJson($path, $post_data);
        }
        catch (\Throwable $e)
        {
            return new BaseObject(-1, 'Payple request failed.');
        }

        if (($output->PCD_PAY_RST ?? '') != "success" || !in_array($output->PCD_PAY_CODE ?? '', ['0000', 'PCOF0000'], true))
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG ?? 'Payple request failed.');
        }

        if (($output->PCD_PAY_OID ?? '') !== 'HT'.str_pad($purchase->purchase_srl, 4, '0', STR_PAD_LEFT)
            || (string) ($output->PCD_PAYER_NO ?? '') !== (string) $purchase->member_srl
            || ($output->PCD_PAY_TYPE ?? '') !== substr($purchase->pay_method, 6)
            || ($output->PCD_PAYER_ID ?? '') !== $PCD_PAYER_ID)
        {
            return new BaseObject(-1, '결제 주문 또는 결제수단이 일치하지 않습니다.');
        }

        if (!self::sameAmount($output->PCD_PAY_TOTAL ?? null, $vars->PCD_PAY_TOTAL))
        {
            return new BaseObject(-1, '결제금액이 일치하지 않습니다. ERR1');
        }

        if (!self::sameAmount($output->PCD_PAY_TOTAL ?? null, $purchase->product_purchase_price))
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response_json);
        $is_success = $http_code == 200 && is_object($output) && ($output->PCD_PAY_RST ?? '') == "success" && in_array($output->PCD_PAY_CODE ?? '', ['PAYC0000'], true);
        if(!$is_success)
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG ?? 'Payple request failed.');
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string);
        curl_setopt($ch, CURLOPT_POST, true);
        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        $output = json_decode($response_json);
        $is_success = $http_code == 200 && is_object($output) && ($output->PCD_PAY_RST ?? '') == "success" && in_array($output->PCD_PAY_CODE ?? '', ['SPCD0000', 'SPAY0000'], true);
        if(!$is_success)
        {
            return new BaseObject(-1, $output->PCD_PAY_MSG ?? 'Payple request failed.');
        }

        $output->purchase_srl = $purchase_srl;

        $billing_obj = new stdClass();
        $billing_obj->error = $is_success ? 0 : -1;
        $billing_obj->message = $output->PCD_PAY_MSG;
        $billing_obj->data = $output;

        return $billing_obj;
    }
}