<?php

/**
 * expression parse
 *
 * Class Expression
 */
class Expression
{

    private $original = null;
    private $originalText = null;

    public function __construct($expression='')
    {
        $expression = ' ' . preg_replace("/\n+/", ' ', $expression) . ' ';
        $this->original = $expression;

    }

    public function getKey(){
        return get_class($this);
    }

    public function getMeta(){
        return ['label'=>$this->getKey()];
    }

    public function calculate($context,$level)
    {
        return eval('return ' . $this->parseExpression($context,$level) . ';');
    }

    private function parseExpression($context,$level)
    {
        $matches = [];
        $pattern = '/\\$([0-9a-zA-z_\\.]+)/';
        if (preg_match_all($pattern, $this->original, $matches)) {
            $expressionCount = count($matches[0]);
            $replacePar = [];
            $replaceParText =[];
            $replaceExpression = [];
            for ($i = 0; $i < $expressionCount; $i++) {
                $replacePar[$matches[0][$i]] = null;
                $replaceParText[$matches[0][$i]] = null;
                $replaceExpression[$matches[0][$i]] = $matches[1][$i];
            }
            foreach ($replaceExpression as $expressKey => $ex) {
                $value = $context->get($ex,$level+1);
                $label = $context->label($ex);
                $replacePar[$expressKey] = $value;
                $replaceParText[$expressKey] = $label;
                $context->set($ex, $value);
            }
            $expression = strtr($this->original, $replacePar);
            $this->originalText = strtr($this->original , $replaceParText);
            return $expression;
        } else {
            return $this->original;
        }
    }
}