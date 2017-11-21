<?php
namespace  CCM;
interface ExpressionInterface {
    public function calculate($context , $level);
    public function getKey();
    public function getMeta();
}