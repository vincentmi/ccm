<?php

namespace Test;

use CCM\Context;
use CCM\CyclicDependenceException;
use CCM\VariableMissingException;
use PHPUnit\Framework\TestCase;

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
            ->reg('aa', '$a +5');
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
        $this->assertEquals([], $this->ctx->getCallstack()); //use cache

    }

    public function testFetch2()
    {
        $this->ctx->reg('m', '$a + $b + $d');
        $this->assertEquals(6, $this->ctx->fetch('m'));
        $this->assertEquals(['m', 'd'], $this->ctx->getCallstack());
        $this->ctx->fetch('m');
        $this->assertEquals([], $this->ctx->getCallstack()); //use cache
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
            ->reg('f',function($ctx,$level) {
                return $ctx->get('d',$level+1) + $ctx->get('e',$level+1);
            })
            ->reg('d','$a + $b')
            ->reg('e','$g + 1')
            ->reg('g',function($ctx,$level) {
                return $ctx->get('a',$level+1) + 1;
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
        $this->assertEquals(['a'=>1,'b'=>'6','c.d'=>16] , $this->ctx->fetchAll('a'));
        $this->assertEquals(['d'=>16] , $this->ctx->fetchAll('a.c'));

    }
}