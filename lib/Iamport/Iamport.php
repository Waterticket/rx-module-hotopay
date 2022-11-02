<?php

class Iamport extends Hotopay {
    public static $IAMPORT_URL = 'https://api.iamport.kr';

    private static $AccessToken = '';
    private static $AccessToken_Expires = 0;

    public function getAccessToken()
    {
        if(self::$AccessToken_Expires == 0)
        {
            if($this->getCache('Iamport_AccessToken'))
            {
                self::$AccessToken = $this->getCache('Iamport_AccessToken');
                self::$AccessToken_Expires = $this->getCache('Iamport_AccessToken_Expires');
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
        $this->deleteCache('Iamport_AccessToken');
        $this->deleteCache('Iamport_AccessToken_Expires');

        self::$AccessToken = '';
        self::$AccessToken_Expires = '';
    }

    private function _RequestAccessToken()
    {
        $config = $this->getConfig();
        $url = self::$IAMPORT_URL.'/users/getToken';
        $ch = curl_init();

        $post_data = [
            'imp_key' => $config->iamport_rest_api_key,
            'imp_secret' => $config->iamport_rest_api_secret,
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $headers = array();
        // $headers[] = 'Accept: application/json';
        // $headers[] = 'Content-Type: application/json';
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

        self::$AccessToken = $result_data->response->access_token;
        self::$AccessToken_Expires = $result_data->response->expired_at - 300; // 5분정도 만료를 앞당김

        $this->setCache('Iamport_AccessToken', self::$AccessToken, self::$AccessToken_Expires - time());
        $this->setCache('Iamport_AccessToken_Expires', self::$AccessToken_Expires, self::$AccessToken_Expires - time());
    }
    
    // 결제내역 단건조회 API
    public function getPaymentByImpUid($imp_uid)
    {
        $accessToken = $this->getAccessToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::$IAMPORT_URL.'/payments/'.$imp_uid);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer '.$accessToken;
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

        $json_data = json_decode($result);
        return $json_data->response;
    }
}