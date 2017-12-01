<?php
namespace  CCM;

/**
 * 重要提示 在perform中只能使用  context->get获取依赖的数据
 * 否则会丢失依赖 context->get(key,1);
 * Interface InterceptorInterface
 * @package CCM
 */
interface InterceptorInterface {
    public function match($context,$keys);
    public function perform($context , $keys,$key);
}