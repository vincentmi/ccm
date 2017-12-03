<?php
namespace Test ;
use CCM\InterceptorInterface;

class InterceptorDepends implements InterceptorInterface{
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
        $mux = $context->get('a');
        $b = $context->get('b');
        $mm = $context->get('mm');

        $context->set('int.a',101 * $mux + $b);
        $context->set('int.b',200 * $mux + $mm);
        $context->addDepends('int.a' , ['a','b','m']);
        $context->addDepends('int.b' , ['a','b','m']);

    }
}