# ccm
Common Calculation Model  . 

## 简介
工作遇到的情况，需要根据一堆数学模型运算产生结果。作为投资的参考。
写了个简单的框架进行管理。

author: miwenshu@gmail.com

blog: http://vnzmi.com

## 特性

- 通过 ```$context->addInterceptor()``` 添加拦截器可以对指定的域进行拦截
实现按需加载参数 ，比如  ```db.project.area``` 载入```project```表的```area```字段载入到上下文中
- 根据```context->fetch``` 获取的数据量，只会运行涉及到该运算的公式。所以虽然整个模型非常庞大
但是只有需要时才会运行相关的公式。
- 如果某一运算涉及的公式非常多 ```rset()```因为会检查变量的依赖会导致进行依赖检查耗费太多时间，建议
使用```reset()```重置后再进行运算。

## 示例代码

```php
cd sample
php common_use.php
php index.php 
php depency_error.php //依赖错误检测
```

## 使用

> 根据天气和工作量调整冰淇淋价格
> 当当天安排给打卡同事的任务不饱和时以及气温比较高的时候调高冰淇淋的售价

```php
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
        return -0.8;
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
    ->set('workload',isset($argv[1])? float($argv[1]):5)
    ->set('todayTemp',isset($argv[2])? float($argv[2]):25)
    ->fetch('price');

echo "\n";

```