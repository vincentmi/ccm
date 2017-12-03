<?php
namespace Test ;
use CCM\InterceptorInterface;


class InterceptorWithDepends implements InterceptorInterface{
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
        $ctx = clone $context ;

        $a = $ctx->get('a');
        $b = $ctx->get('b');
        $c = $ctx->get('c');

        $context->set('int.a',10 * $a);
        $context->set('int.b',100 * $b);
        $context->set('int.c',1000 * $c);
        $context->addDepends('int.a' , ['a']);
        $context->addDepends('int.b' , ['b']);
        $context->addDepends('int.c' , ['c']);

    }
}