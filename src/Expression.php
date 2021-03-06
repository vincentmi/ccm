<?php
namespace  CCM;
/**
 * expression parse
 *
 * Class Expression
 */
class Expression implements ExpressionInterface
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

    public function calculate($context)
    {
        return eval('return ' . $this->parseExpression($context) . ';');
    }

    private function parseExpression($context)
    {
        $matches = [];
        $pattern = '/\\$([0-9a-zA-z_\\.]+)\b/';
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
                $value = $context->get($ex);
                if(is_array($value)){
                    $value = '('.var_export($value,true) . ')';
                }else if(is_null($value)){
                    $value = 0;
                }
                $label = $context->label($ex);
                $replacePar[$expressKey] = $value;
                $replaceParText[$expressKey] = $label;
            }
            $expression = strtr($this->original, $replacePar);
            $this->originalText = strtr($this->original , $replaceParText);
            return $expression;
        } else {
            return $this->original;
        }
    }
}