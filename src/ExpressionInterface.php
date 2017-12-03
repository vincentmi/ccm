<?php
namespace  CCM;
interface ExpressionInterface {
    public function calculate($context);
    public function getKey();
    public function getMeta();
}