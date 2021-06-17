<?php

class Paypal extends Hotopay {
    public static $PAYPAL_URL = 'https://api-m.sandbox.paypal.com';

    private static $AccessToken = '';
    private static $AccessToken_Expires = 0;

    public function getAccessToken()
    {
        if(self::$AccessToken_Expires == 0)
        {
            if(isset($_SESSION['Paypal_AccessToken_Expires']))
            {
                self::$AccessToken = $_SESSION['Paypal_AccessToken'];
                self::$AccessToken_Expires = $_SESSION['Paypal_AccessToken_Expires'];
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
        if(isset($_SESSION['Paypal_AccessToken_Expires']))
        {
            unset($_SESSION['Paypal_AccessToken']);
            unset($_SESSION['Paypal_AccessToken_Expires']);
        }

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
        curl_close($ch);

        $result_data = json_decode($result);

        self::$AccessToken = $result_data->access_token;
        self::$AccessToken_Expires = time() + $result_data->expires_in - 60; // 1분정도 만료를 앞당김

        $_SESSION['Paypal_AccessToken'] = self::$AccessToken;
        $_SESSION['Paypal_AccessToken_Expires'] = self::$AccessToken_Expires;
    }
}