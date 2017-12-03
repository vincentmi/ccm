<?php

use CCM\InterceptorInterface;

/**
 *  interceptor示例
 * Class SampleInterceptor
 */
class SampleInterceptor implements InterceptorInterface{
    public function match($context, $keys)
    {
        if($keys[0] == 'int'){
            return true;
        }else{
            return false;
        }
    }

    public function reset(){

    }

    public function perform($context, $keys, $key)
    {
        //克隆原上下文避免污染调用栈
        $ctx = clone $context ;

        $a = $ctx->get('a');
        $b = $ctx->get('b');
        $c = $ctx->get('c');

        $context->set('int.a',10 * $a);
        $context->set('int.b',100 * $b);
        $context->set('int.c',1000 * $c);

        //手动配置调用关系
        $context->addDepends('int.a' , ['a']);
        $context->addDepends('int.b' , ['b']);
        $context->addDepends('int.c' , ['c']);

    }
}