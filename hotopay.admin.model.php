<?php

/**
 * Hoto Pay
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class HotopayAdminModel extends Hotopay
{
	public function getSales($startPeriod = 0, $endPeriod = -1)
    {
        $args = new stdClass();
        $args->startPeriod = $startPeriod;
        
        if($endPeriod != -1) $args->endPeriod = $endPeriod;
        $output = executeQuery('hotopay.getSalesAmount', $args);
        
        return $output->data;
    }
}
