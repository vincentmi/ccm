<?php

namespace Test;

use CCM\Context;
use CCM\CyclicDependenceException;
use CCM\InterceptorInterface;
use CCM\VariableMissingException;
use PHPUnit\Framework\TestCase;

class InteceptorDepends implements InterceptorInterface{
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
        $mux = $context->get('a',0);
        $b = $context->get('b',0);
        $mm = $context->get('mm',0);

        $context->set('int.a',101 * $mux + $b);
        $context->set('int.b',200 * $mux + $mm);

    }
}


class MainTest extends TestCase
{
    public $ctx = null;

    public function setUp()
    {
        parent::setUp();
        $ctx = new Context();
        $ctx->set(['a' => 1, 'b' => 2, 'c' => 3])
            ->reg('d', '$a + $b')
            ->reg('e', '$a * $b')
            ->reg('aa', '$a +5')
            ->reg('mm','$c + 2');
        $this->ctx = $ctx;
    }

    public function testSet()
    {
        $this->assertEquals(1, $this->ctx->get('a', 0));
        $this->assertEquals(2, $this->ctx->get('b', 0));
        $this->assertEquals(2, $this->ctx->get('b', 0));
        $this->ctx->set('b', 4);
        $this->assertEquals(4, $this->ctx->get('b', 0));
    }

    public function testFetch()
    {

        $this->assertEquals(3, $this->ctx->fetch('c'));
        $this->assertEquals(3, $this->ctx->fetch('d'));
        $this->ctx->fetch('d');
        //$this->assertEquals([], $this->ctx->getCallstack()); //use cache

    }

    public function testFetch2()
    {
        $this->ctx->reg('m', '$a + $b + $d');
        $this->assertEquals(6, $this->ctx->fetch('m'));
        $this->assertEquals(['m', 'a','b','d','a','b'], $this->ctx->getCallstack());
        $this->ctx->fetch('m');

        $this->assertEquals(['m'], $this->ctx->getCallstack()); //use cache
    }

    public function testCycleDep()
    {
        try {
            $this->ctx
                ->reg('k', '$i + $a')
                ->reg('i', '$d + 1');
            $this->ctx->fetch('k');
            $this->assertEquals(1, 1);
        } catch (\Exception $e) {
            $this->assertEquals(get_class($e), CyclicDependenceException::class);
        }

    }

    public function testMiss()
    {
        try {
            $this->ctx->reg('f', '$m + 1');
            $this->ctx->fetch('b');
            $this->assertEquals(1, 1);
        } catch (\Exception $e) {
            $this->assertEquals(get_class($e), VariableMissingException::class);
        }

    }

    public function testRset1(){
        $c = new Context();
        $c->set('a',1)
            ->set('b',2)
            ->reg('f','$d + $e')
            ->reg('d','$a + $b')
            ->reg('e','$g + 1')
            ->reg('g','$a + 1 ')
            ->fetch('f');
        //$c->printCallstack();
        $this->assertEquals(6,$c->fetch('f'));
        $this->assertEquals(3,$c->fetch('e'));
        $c->rset('a',100);
        $this->assertEquals(204,$c->fetch('f'));
        $this->assertEquals(102,$c->fetch('e'));
    }
    public function testRset2(){
        $c = new Context();
        $c->set('a',1)
            ->set('b',2)
            ->reg('f',function($ctx) {
                return $ctx->get('d') + $ctx->get('e');
            })
            ->reg('d','$a + $b')
            ->reg('e','$g + 1')
            ->reg('g',function($ctx) {
                return $ctx->get('a') + 1;
            })
            ->fetch('f');
        $this->assertEquals(6,$c->fetch('f'));
        $this->assertEquals(3,$c->fetch('e'));
        $c->rset('a',100);
        $this->assertEquals(204,$c->fetch('f'));
        $this->assertEquals(102,$c->fetch('e'));
    }

    public function testRset()
    {
        $this->ctx->reg('f', '$d + $e');
        $this->assertEquals(5, $this->ctx->fetch('f'));
        $this->assertEquals(6, $this->ctx->fetch('aa'));
        //$this->fail(print_r($this->ctx,true));
        //print_r($this->ctx->getDepends());
        $this->ctx->rset('a', 10);
        $this->assertEquals(32, $this->ctx->fetch('f'));
        $this->assertEquals(15, $this->ctx->fetch('aa'));
    }

    public function testZeroDivisionDebugOff(){

        $this->ctx->reg('f1','5/0');
        $this->ctx->debug(false);
        $this->assertEquals(0 ,$this->ctx->fetch('f1'));
    }

    /**
     * @expectedException \ErrorException
     * @expectedExceptionMessage Division by zero
     */

    public function testZeroDivisionDebugOn(){
        $this->ctx->reg('f1','5/0');
        $this->ctx->debug(true);
        $this->ctx->fetch('f1');
    }

    public function testFetchAll(){
        $this->ctx->set('a.a',1);
        $this->ctx->reg('a.b','$a.a + 5');
        $this->ctx->reg('a.c.d','$a.a + 15');
       // $this->assertEquals(['a'=>1,'b'=>'6','c.d'=>16] , $this->ctx->fetchAll('a'));
        $this->assertEquals(['d'=>16] , $this->ctx->fetchAll('a.c'));

    }


    public function testPrintCall(){
        $this->ctx->debug(true);
        $this->assertEquals([] , $this->ctx->getCalls('e'));
        $e1 = $this->ctx->fetch('e');

        $this->assertEquals(
            [
                [ 'key'=>'e','level'=>0,'data'=>2] ,
                [ 'key'=>'a','level'=>1,'data'=>1],
                [ 'key'=>'b','level'=>1,'data'=>2]],$this->ctx->getCalls('e'));

        //$this->ctx->printCalls('e');

        $this->ctx->rset('a',2);
        $e2 = $this->ctx->fetch('e');
        $this->assertEquals(2,$e1);
        $this->assertEquals(4,$e2);
        //$this->ctx->printCalls('e');

        $this->getActualOutput();

    }

    public function testInteceptorDepends(){
        $inteceptor = new InteceptorDepends();
        //int.a = 101*a+b int.b=200*a + mm a=1 b=2
        $this->ctx->addInterceptor($inteceptor);
        $this->ctx->reg('dep','$int.a + $b');
        $this->assertEquals(105 ,$this->ctx->fetch('dep'));

        $this->ctx->rset('a','2');
        $this->assertEquals(206 ,$this->ctx->fetch('dep'));
    }

    public function testInteceptorDepends2(){
        $inteceptor = new InteceptorDepends();
        //int.a = 101*a+b int.b=200*a + mm
        $this->ctx->addInterceptor($inteceptor);
        $this->ctx->reg('dep','$int.a + $b');

        $this->assertEquals(105 ,$this->ctx->fetch('dep'));

        $this->ctx->rset('b','3');
        $this->assertEquals(107 ,$this->ctx->fetch('dep'));
    }

    public function testInteceptorDepends3(){
        $inteceptor = new InteceptorDepends();
        //int.a = 101*a+b int.b=200*a + mm
        $this->ctx->addInterceptor($inteceptor);
        $this->ctx->debug(true);

        $this->ctx->reg('dep','$int.b + $b');
        $this->ctx->reg('dep2','$int.a + $b');

        $this->assertEquals(207 ,$this->ctx->fetch('dep'));

        $this->ctx->rset('c','13');
        $this->assertEquals(217,$this->ctx->fetch('dep'));
    }


}