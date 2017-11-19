<?php
namespace CustomExpress;

class SampleExpression extends \Expression
{
    public function calculate($context,$level)
    {
        if($context->get('base.rent.cac',$level+1) == 1){
            return 1000;
        }else{
            return 200;
        }
    }

    public function getKey(){
        return 'cal.sample';
    }

    public function getMeta()
    {
        return '有中央空调一千';
    }

}