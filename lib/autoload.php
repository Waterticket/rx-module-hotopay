<?php

/*
 *  Hotopay Library Autoloader
 *  @author Waterticket
 */

spl_autoload_register(function($className) {
    $target = array(
        "Paypal" => "Paypal/Paypal.php",
        "Toss" => "Toss/Toss.php",
        "KakaoPay" => "KakaoPay/KakaoPay.php",
        "Iamport" => "Iamport/Iamport.php",
        "Payple" => "Payple/Payple.php",
        "HotopayLib\Currency\driver\Fixer" => "Currency/driver/Fixer.php",
        "HotopayLib\Currency\driver\Exchangeratehost" => "Currency/driver/Exchangeratehost.php",
    );

    if(array_key_exists($className, $target))
        include __DIR__ . '/' . $target[$className];
});