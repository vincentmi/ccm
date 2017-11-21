<?php

namespace CCM;
/**
 * Calculation Context
 * result cache and provide a depends check
 * with lazy-load support
 *
 * @author miwenshu@gmail.com
 */
class CCMException extends \Exception
{
}

/**
 * 缺少公式运行的变量
 * @package CCM
 */
class VariableMissingException extends CCMException
{

}

/**
 * Class InvalidMapException
 * @package CCM
 */
class InvalidException extends CCMException
{

}

class CyclicDependenceException extends CCMException
{

}

class KeyOccupiedException extends CCMException
{

}


class Context
{

    public $prefix = 'cal.';

    public $prefixLength = 4;
    public $rsetCount = 0;
    private $fields = [];
    private $cache = [];
    private $map = [];
    private $callstack = [];
    private $callstackLevel = [];
    private $metas = [];
    private $interceptors = [];
    private $depends = [];

    public function gets($keys, $level, $replaceDot = false)
    {
        if (!is_array($keys)) {
            $keys = explode(',', $keys);
        }
        $data = [];
        foreach ($keys as $key) {
            if ($replaceDot) {
                $k = str_replace('.', '_', $key);
            } else {
                $k = $key;
            }
            $data[$k] = $this->get($key, $level);
        }
        return $data;
    }

    /**
     * 获取指定key的值，用于公式内部运算
     * 自定义函数需要使用该方法来获取值
     * 以便保持依赖关系
     * @param $key
     * @return mixed|null
     * @throws Exception
     */
    public function get($key, $level)
    {
        $key = trim($key);

        //interceptor
        $keys = explode('.', $key);
        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->match($this, $keys)) {
                $interceptor->perform($this, $keys, $key);
            }
        }

        if (isset($this->fields[$key])){
            $callStackLen = count($this->callstack);
            if($callStackLen > 0) {
                $pKey = $this->callstack[$callStackLen-1];
                $this->depends[$key][$pKey] = $pKey;
            }
            return $this->fields[$key];
        }


        //callstack check
        if (in_array($key, $this->callstack)) {
            array_push($this->callstack, $key);
            array_push($this->callstackLevel, $level);

            $text = "Cyclic dependence error occur in resolve '$key'. please check call stack below：\n";
            $text .= $this->printCallstack(true);
            $text .= "\n";
            throw new CyclicDependenceException($text);
        }
        array_push($this->callstack, $key);
        array_push($this->callstackLevel, $level);

        if (isset($this->map[$key])) {
            $dependStart = count($this->callstack);
            $mapped = $this->map[$key];
            if (is_object($mapped) && is_a($mapped, ExpressionInterface::class)) {
                $value = $mapped->calculate($this, $level + 1);
            } else {
                if (is_callable($mapped)) {
                    $value = call_user_func_array($this->map[$key], [$this, $level + 1]);
                } else {
                    throw new InvalidException('invalid key map' . var_export($mapped, true));
                }
            }
            $keyDepend = array_slice($this->callstack, $dependStart);

            foreach ($keyDepend as $dependKey) {
                if (!isset($this->depends[$dependKey])) {
                    $this->depends[$dependKey] = [];
                }
                $this->depends[$dependKey][$key] = $key;

            }

            $this->set($key, $value);
            return $value;

        } else {
            //print_r($this);
            throw new VariableMissingException('variable\'' . $key . '\' missed ');
            //return null;
        }
    }

    public function printCallstack($return = false)
    {
        $text = '';
        foreach ($this->callstack as $index => $item) {
            $text .= "|-" . str_repeat('--', $this->callstackLevel[$index]) . $item . "\n";
        }
        $text .= "\n";

        if ($return == true) {
            return $text;
        } else {
            echo $text;
        }
    }

    /**
     * 设置公式结果以及常量值
     * 该函数不进行依赖检查
     * @param \mix $key
     * @param string $value
     * @param string $label
     * @return $this
     */
    public function set($key, $value = '', $meta = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->fields[$k] = $v;
            }
        } else {
            $this->fields[$key] = $value;
        }

        $this->setMeta($key, $meta);
        return $this;
    }

    private function setMeta($key, $meta)
    {
        if ($meta) {
            if (is_string($meta)) {
                $meta = ['label' => $meta];
            }
            $this->metas[$key] = $meta;
        }
    }

    /**
     * @param $interceptor
     * @param string $alias
     * @return $this
     */
    public function addInterceptor($interceptor, $alias = '')
    {
        if ($alias != '') {
            if (isset($this->interceptors[$alias])) {
                trigger_error(E_USER_NOTICE, 'interceptor added  skiped.');
                return $this;
            }
        }
        if (is_a($interceptor) == InterceptorInterface::class) {
            $this->interceptors[] = $interceptor;
        }
        return $this;
    }

    public function getCallstack()
    {
        return $this->callstack;
    }

    public function getDepends($key = null)
    {
        if ($key) {
            return isset($this->depends[$key]) ? $this->depends[$key] : [];
        } else
            return $this->depends;
    }

    public function getMap($key = null)
    {
        if ($key) {
            return isset($this->map[$key]) ? $this->map[$key] : [];
        } else
            return $this->map;
    }

    /**
     * 删除域内的变量
     * @param $domain
     */
    public function remove($domain)
    {
        $domainLength = count($domain);
        foreach ($this->fields as $k => $v) {
            if (substr($k, 0, $domainLength) == $domain) {
                unset($this->fields[$k]);
            }
        }
        return $this;
    }

    /**
     * 删除域内注册的公式
     * @param $domain
     */
    public function unreg($domain)
    {
        $domainLength = count($domain);
        foreach ($this->map as $k => $v) {
            if (substr($k, 0, $domainLength) == $domain) {
                unset($this->map[$k]);
            }
        }
        return $this;
    }

    /**
     * 重置context以及置顶前缀的运算结果
     * @param bool $deep
     * @return array
     */
    public function reset($deep = false)
    {
        if ($deep === true) {
            $this->fields = [];
        } else {
            $cal = [];
            foreach ($this->fields as $k => $v) {
                $prefix = substr($k, 0, $this->prefixLength);
                if ($prefix == $this->prefix) {
                    $cal[$k] = $v;
                    unset($this->fields[$k]);
                }
            }
        }
        $this->callstack = [];
        $this->depends = [];
        return $this;
    }

    /**
     * 重设变量，会清除依赖的公式的运算结果
     * @param $key
     * @param $value
     */
    public function rset($key, $value = null)
    {
        if (is_array($key)) {
            $this->rsetCount = 0;
            foreach ($key as $k => $v) {
                $this->clearDepends($k);
            }
            $this->set($key);
        }else{
            $this->rsetCount = 0;
            $this->clearDepends($key);
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * 清楚依赖
     * @param $key
     * @return  $this
     */
    private function clearDepends($key)
    {
        if (isset($this->depends[$key])) {
            foreach ($this->depends[$key] as $child) {
                unset($this->fields[$child]);
                $this->rsetCount++;
                //echo 'unset '.$child."\n";
                $this->clearDepends($child);
            }
        }
        return $this;
    }

    /**
     * 读取多个属性以及部分meta
     * @param $keys
     * @return array
     */
    public function fetchs($keys)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = [
                'label' => $this->label($key),
                'value' => $this->fetch($key),
                'tips' => $this->tips($key)
            ];
        }
        return $data;
    }

    /**
     * 获取注册公式的标题
     * @param $key
     * @return mixed
     */
    public function label($key)
    {
        if (isset($this->metas[$key])) {
            return isset($this->metas[$key]['label']) ? $this->metas[$key]['label'] : $key;
        } else {
            return $key;
        }
    }

    /**
     * 清除调用堆栈，并获取运算结果
     * @param $key
     * @return mixed|null
     */
    public function fetch($key)
    {
        $this->callstack = [];
        return $this->get($key, 0);
    }

    /**
     * 获取注册公式的说明
     * @param $key
     * @return mixed
     */
    public function tips($key)
    {
        if (isset($this->metas[$key])) {
            return isset($this->metas[$key]['tips']) ? $this->metas[$key]['tips'] : '';
        } else {
            return '';
        }
    }

    /**
     * 进行公式运算
     * @param $expression
     * @return mixed
     */
    public function calc($expression)
    {
        $tmp = new Expression($expression);
        return $tmp->calculate($this);
    }

    /**
     * 注册一个class , key和 meta都从类中读取
     * @param $class
     * @return $this
     */
    public function regClass($class)
    {
        $class = new $class;
        $this->reg($class->getKey(), $class, $class->getMeta());
        return $this;
    }

    /**
     * 注册一个公式
     * @param $key
     * @param string|callable|ExpressionInterface $expression
     * @param string $label
     * @return $this
     * @throws Exception
     */
    public function reg($key, $expression, $meta = null)
    {
        if (isset($this->map[$key])) {
            throw new KeyOccupiedException('The requested key [' . $key . '] has been registered.');
        }

        if (is_string($expression)) {
            $expression = new Expression($expression);
            $this->map[$key] = $expression;
        } else if (is_object($expression) && is_a($expression, ExpressionInterface::class)) {
            $this->map[$key] = $expression;
        } else if (is_callable($expression)) {
            $this->map[$key] = $expression;
        } else {
            throw new InvalidException('Invalid expression for \'' . $key . '\'');
        }

        $this->setMeta($key, $meta);
        return $this;
    }

}