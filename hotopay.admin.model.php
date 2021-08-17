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

    public function setNumberComp($var, $decimal_point = 0)
    {
        if($var > 0)
            return '▲ '.number_format($var, $decimal_point);
        else if($var < 0)
            return '▼ '.number_format(abs($var), $decimal_point);
        else
            return 'ㅡ '.number_format($var, $decimal_point);
    }

    public function getNumberStatus($var)
    {
        if($var > 0)
            return 'positive';
        else if($var < 0)
            return 'negative';
        else
            return 'neutral';
    }

    public function getPercentage($value, $compare = 0)
    {
        if($value + $compare == 0) return '0%';
        if($compare == 0) $compare = 1;
        if($value == $compare) return '100%';

        return $this->setNumberComp(($value-$compare)/$compare * 100 + 100, 1) . '%';
    }
}
