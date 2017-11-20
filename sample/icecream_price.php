<?php
require __DIR__."/../vendor/autoload.php";
use CCM\Context;
$ctx = new \CCM\Context();

echo
$ctx->reg('temperature_rate' ,function($context,$level){

    $todayTemp = $context->get('todayTemp' , $level+1) ; // $todayTemp = fget('http://cnweathor.com/getToday')
    if($todayTemp > 30){
        return 0.2;
    }else if($todayTemp > 40){
        return 1;
    }else if($todayTemp < 25){
        return -0.2;
    }
} )
    ->reg('workload_rate',function($context , $level){
        $workload = $context->get('workload',$level+1);
        $totalCheckin = $context->get('totalCheckin',$level+1);
        if($workload/$totalCheckin < 1){
            return 0.3;
        }else{
            return -0.2;
        }
    })
    ->reg('price' , '$org_price * (1 + $temperature_rate + $workload_rate)')
    ->set('org_price',3)
    ->set('totalCheckin',5)
    ->set('workload',isset($argv[1])? intval($argv[1]):5)
    ->set('todayTemp',isset($argv[2])? intval($argv[2]):25)
    ->fetch('price');

echo "\n";