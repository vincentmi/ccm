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
- **特别注意,字符串形式的公式表达式和变量之间必须要有空格,例如: ```$a+$b``` 需要些成 ```$a + $b```**

## 使用

修改```composer.json```

```json
composer require vincentmi/ccm

//或者
手动修改项目的 composer.json文件

"require": {
    	"vincentmi/ccm": "1.*"
}

```
更新依赖包

```sh
composer update
```

测试代码

```php
<?php
require  'vendor/autoload.php';
use CCM\Context;

$ctx =  new Context();
echo $ctx->set('a',1)->set('b',1)->reg('c','$a + $b')->fetch('c');

```

## 集成到laravel

添加provider ```\CCM\Laravel\ServiceProvider::class,``` 到 ```configs/app.php```
,添加 alias ```'CCM'=>\CCM\Laravel\CCM::class```.

使用 

```
CCM::set('a',1);
CCM::reg('b','$a + 10');

echo CCM::fetch('b');
```


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
    }else{
        return 0;
    }
} )
    ->reg('workload_rate',function($context , $level){
        $workload = $context->get('workload',$level+1);
        $totalCheckin = $context->get('totalCheckin',$level+1);
        $workloadRate = $workload/$totalCheckin;
        if( $workloadRate < 1){
            return 0.3;
        }else if($workloadRate < 0.5){
            return 0.5;
        }else if($workloadRate == 1){
            return 0;
        }else{
            return -0.2;
        }
    })
    ->reg('price' , '$org_price * (1 + $temperature_rate + $workload_rate)')
    ->set('org_price',3)
    ->set('totalCheckin',5)
    ->set('workload',isset($argv[1])? floatval($argv[1]):5)
    ->set('todayTemp',isset($argv[2])? floatval($argv[2]):25)
    ->fetch('price');

echo "\n";

```