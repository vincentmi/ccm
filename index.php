<?php
include 'Context.php';
include 'Expression.php';
include 'SampleExpression.php';

$context = new Context();

$context->set('base.rent.area',1 , '租赁总面积');
$context->set('base.rent.cac',1,'是否有中央空调');
$context->set('base.rent.direct_water',1,'是否有直饮水');
$context->set('base.rent.warm',1,'取暖费用');
$context->set('base.biz.rent_start_date','2017-12-01','起租日');
$context->set('base.biz.rent_free','3','免租期');
$context->set('base.station_retail',500,'工位月租金');
$context->set('exp.station_retail_rate',5,'工位租金增长率');
$context->regClass(CustomExpress\SampleExpression::class);

$context->reg('cal.station_retail' ,
    ' 
    $base.station_retail 
    * 
    pow(
        1+ $exp.station_retail_rate /100, 
        $cal.year_offset 
    ) + $base.rent.area'
,'工位单位月租金');

$context->set('cal.date','2017-02-01','计算日期');

$context->reg('cal.year_offset',function($ctx,$level){
    $date = strtotime($ctx->get('base.biz.rent_start_date',$level+1));
    $date2 = strtotime($ctx->get('cal.date',$level+1));
    return intval(date('Y',$date2) - date('Y',$date));
});

$context->reg('cal.year_rent_amount',' $base.rent.area * 12 * $cal.station_retail ',
    ' 年度工位收入');

$context->reg('cal.year_rent_amount2',' $base.rent.area * 12 * $cal.station_retail +1000 ',
    ' 年度工位收入2');


//$expression = ' ${base.rent.area} * 12 * ${cal.station_retail} ';

for($i = 2016 ;$i<=2025;$i++){
    $context->rset('cal.date',$i.'-02-01');
    echo $context->rsetCount ."\n\n";
    echo $i.$context->label('cal.year_rent_amount').':' .
        $context->fetch('cal.year_rent_amount')
        .' '.$context->label('cal.station_retail').'='.$context->fetch('cal.station_retail')
        ." +++ ";
    echo $context->fetch('cal.year_rent_amount2')
    .' '.$context->label('cal.station_retail').'='.$context->fetch('cal.station_retail')
    ."\n";

}

echo (json_encode($context->fetchs('cal.sample'),JSON_UNESCAPED_UNICODE));

echo "\n";

print_r($context->getMap('cal.year_rent_amount'));

echo "\n";