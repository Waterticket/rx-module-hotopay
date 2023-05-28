<?php
namespace HotopayLib\Currency\driver;

class Exchangeratehost {
    public function __construct() {
    }

    public function getLatestCurrency(string $base = 'USD', array $symbols = ['KRW','JPY','CNY','EUR','USD'])
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.exchangerate.host/latest?symbols=".implode(',', $symbols)."&base=$base",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200)
            throw new \Exception("Exchangeratehost API Error: $response");

        curl_close($curl);
        $result = json_decode($response);

        if (!$result->success)
            throw new \Exception("Exchangeratehost API Error: $response");

        $arranged_data = array();
        $base = $result->base;
        foreach ($result->rates as $key => $value) {
            $arranged_data[] = array(
                "base_currency" => $base,
                "base_value" => 1,
                "target_currency" => $key,
                "target_value" => $value,
            );
        }

        return $arranged_data;
    }
}