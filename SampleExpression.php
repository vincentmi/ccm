<?php
namespace CustomExpress;

class SampleExpression extends \Expression
{
    public function calculate($context)
    {
        if($context->get('base.rent.cac') == 1){
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