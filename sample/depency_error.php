<?php
require __DIR__."/../vendor/autoload.php";

require 'SampleExpression.php';

use CCM\Context;

$context = new Context();

$context
    ->set('a1',1)
    ->set('a2',66)
    ->set('rate',0.5)
    ->set('base.b',2)
    ->reg('base.a',function ($context , $level){
        return $context->get('a1',$level+1) + $context->get('a2',$level+1);
    })
    ->reg('base.m','$base.a * $base.b')
    ->reg('cal.c','$base.a + $base.b')
    ->regClass(\CustomExpress\SampleExpression::class)
    ->reg('cal.d',function ($context,$level){
        $m = $context->get('base.m',$level+1);
        $a = $context->get('base.a',$level+1);
        if($m > 100){
            return $a * 0.5;
        }else{
            return $a +10;
        }
    });

echo 'cal.d='.$context->fetch('cal.d')."\n";
$context->printCallstack();


