<?php
require __DIR__."/../vendor/autoload.php";

require 'SampleExpression.php';
date_default_timezone_set('Asia/Shanghai');


use CCM\Context;

$context = new Context();

$context->set('a',1 , '租赁总面积');
$context->set('b',2 , '租赁总面积');
$context->reg('c','$a + $b');

$context->reg('d' , '$a + $b + $c');

echo 'd1_0=' . $context->fetch('d')."\n";

$context->printCallstack();

print_r($context->getDepends());

echo 'd1_1=' . $context->fetch('d')."\n";
$context->printCallstack();

$context->rset('a',5);
echo 'd5_0=' . $context->fetch('d')."\n";
$context->printCallstack();
echo 'd5_1=' . $context->fetch('d')."\n";
$context->printCallstack();


