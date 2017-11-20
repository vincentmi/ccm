<?php
namespace  CCM;
interface ExpressionInterface {
    public function calculate($context , $level);
}