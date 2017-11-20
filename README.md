# ccm
Common Calculation Model  . 

## 简介
用于一堆数学模型运算结果

miwenshu@gmail.com

http://vnzmi.com

## 使用

> 根据天气和工作量调整冰淇淋价格

```
<?php
require __DIR__."/../vendor/autoload.php";
use CCM\Context;
$ctx = new \CCM\Context();

$ctx->reg('temperature_rate' ,function($context,$level){

    $todayTemp = 35 ; // $todayTemp = fget('http://cnweathor.com/getToday')
    if($todayTemp > 30){
        return 0.2;
    }else if($todayTemp > 40){
        return 1;
    }else if($todayTemp < 25){
        return -0.8;
    }
} )
->reg('workload_rate',function($context , $level){
    $workload = $context->get('workload',$level+1);
    $totalCheckin = $context->get('totalCheckin',$level+1);
    if($workload/$totalCheckin < 1){
        return 0.2;
    }else{
        return 0;
    }
})
    ->reg('price' , function($context , $level){
        $temperature_rate = $context->get('temperature_rate',$level+1);
        $workload_rate = $context->get('workload_rate',$level+1);
        $org_price = 3;

        return $org_price * (1 + $temperature_rate + $workload_rate);
    })
;


if(isset($argv[1]) ){
    $ctx->set('workload',$argv[1]);
}else{
    $ctx->set('workload',5);
}
echo $ctx->set('totalCheckin',5)
    ->fetch('price')."\n";

```