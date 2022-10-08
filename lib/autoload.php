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
        "Inicis" => "Inicis/Inicis.php",
    );

    if(array_key_exists($className, $target))
        include __DIR__ . '/' . $target[$className];
});