<?php
namespace  CCM;
interface InterceptorInterface {
    public function match($context,$keys);
    public function perform($context , $keys,$key);
}