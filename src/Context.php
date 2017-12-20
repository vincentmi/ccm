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
    public $rsetCount = 0;
    protected $fields = [];
    protected $map = [];
    //调用栈用于进行关联关系和循环调用检查
    protected $callstack = [];
    //用于调试的调用
    protected $_callstack = [];
    protected $_callstackLevel = [];
    protected $_callstackValue = [];
    /**
     * debug时的全部callstacks
     * @var array
     */
    protected $_calls = [];
    protected $metas = [];
    protected $interceptors = [];
    /**
     *   parent=>[childs]
     * @var array
     */
    protected $depends = [];

    /**
     * debug模式会拦截一些错误 比如 除数为0
     * @var bool
     */
    protected $_debug = false;

    /**
     * 如果打开 debug可以使用 getCalls(key)获取最近一次fetch的执行过程
     * 如果不开启debug可以使用 printDependTree(key)获取key的依赖关系
     * @param bool $debug
     * @return $this
     */
    public function debug($debug = true){
        $this->_debug = $debug;
        return $this;
    }

    /**
     * 检查是否已经换成指定的key
     * @param $key
     * @return bool
     */
    public function hasField($key){
        return isset($this->fields[$key]);
    }

    public function gets($keys,$replaceDot = false)
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
            $data[$k] = $this->get($key);
        }
        return $data;
    }

    public function get($key,$default=null){
        if(is_array($key)){
            $data = [];
            foreach($key as  $k){
                $def  = is_array($default) && isset($default[$k])? $default[$k] : null;
                $data[$k] = $this->_get($k,$def);
            }
        }else{
            $data = $this->_get($key,$default);
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
    private  function _get($key, $default = null)
    {
        $key = trim($key);

        if (in_array($key, $this->callstack)) {
            $text = "Cyclic dependence error occur in resolve '$key'. please check call stack below：\n";
            $text .= $this->printCallstack();
            $text .= "\n";
            throw new CyclicDependenceException($text);
        }

        $level = count($this->callstack);
        $pKey = $level > 0 ? $this->callstack[$level - 1] : '';

        array_push($this->callstack , $key);
        $this->_callstack[] = $key;
        $this->_callstackLevel[] = $level;
        $this->_callstackValue[] = '$'.$key;
        $valueIndex = count($this->_callstackValue) -1 ;

        if (isset($this->fields[$key])){
            $value = $this->fields[$key];
            if($value === 'nil'){
                $value = null ;
            }
        }else {
            //interceptor
            $matched = false;
            $keys = explode('.', $key);
            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->match($this, $keys)) {
                    $interceptor->perform($this, $keys, $key);
                    $matched = true;
                }
            }
            if ($matched == true && isset($this->fields[$key])) {
                $value = $this->fields[$key];
                //loaded by interceptor
            } else {
                if (isset($this->map[$key])) {
                    $mapped = $this->map[$key];
                    $value = 0;
                    try {
                        $oldErrorHandler = null;
                        if ($this->_debug == false) {
                            $oldErrorHandler = set_error_handler(function ($severity, $message, $file, $line) {
                                throw new \ErrorException($message, 0, $severity, $file, $line);
                            });
                        }

                        if (is_object($mapped) && is_a($mapped, ExpressionInterface::class)) {
                            $value = $mapped->calculate($this);
                        } else {
                            if (is_callable($mapped)) {
                                $value = call_user_func_array($this->map[$key], [$this]);
                            } else {
                                throw new InvalidException('invalid key map' . var_export($mapped, true));
                            }
                        }
                        if ($oldErrorHandler) {
                            set_error_handler($oldErrorHandler);
                        }

                    } catch (\Exception $e) {
                        if ($this->_debug) {
                            throw $e;
                        }
                    }

                } else {
                    //print_r($this);
                    if($default === null){
                        throw new VariableMissingException('variable\'' . $key . '\' missed ');
                    }else{
                        return $default;
                        //use default value no cache
                    }

                    //return null;
                }
            }
            $this->set($key, $value);
        }
        array_pop($this->callstack);
        if($pKey){
            $this->addDepends($pKey,$key);//上级依赖当前调用
        }
        $this->_callstackValue[$valueIndex] = $value;
        return $value;
    }

    public function getDependTree($key)
    {
        $ctx = clone $this;
        $data = [
            ['key'=>$key,'level'=>0 ,'data'=>$ctx->fetch($key)]
        ];
        $this->_getDependTree($data , $key,1,$ctx);
        return $data;
    }

    private function _getDependTree(&$result, $key , $level=0 ,$ctx)
    {
        $parents = [];
        foreach($this->depends as $parent=> $child)
        {
            if(isset($child[$key]))
            {
                $parents[] = $parent;
                $result[] =['key'=>$parent , 'level'=>$level ,'data'=>$ctx->fetch($parent)];
                $this->_getDependTree($result , $parent,$level+1 , $ctx);
            }
        }
    }


    public function printDependTree($key,$return = false)
    {
        $tree = $this->getDependTree($key);
        $text = '';
        foreach ($tree as $index => $item) {
            $data = isset($item['data']) ? $item['data'] : '?';
            $data = is_scalar($data) ? strval($data) : json_encode($data,JSON_UNESCAPED_UNICODE);
            $text .=  str_repeat('  ', $item['level']) .'|-'. $item['key'] . '='.$data."\n";
        }
        $text .= "\n";

        if ($return == true) {
            return $text;
        } else {
            echo $text;
        }
    }



    /**
     * 设置 $key 依赖的 底层key,
     * 使用Interceptor进行载入时 需要 在载入后 手动维护依赖关系
     * @param $key
     * @param $subCalls
     * @return $this
     */
    public function addDepends($key , $depends){
        if($key == ''){
            return ;
        }
        if(!is_array($depends)){
            $depends = [$depends];
        }
        foreach($depends as $depend){
            if (!isset($this->depends[$depend])) {
                $this->depends[$depend] = [$key=>$key];
            }else{
                $this->depends[$depend][$key] = $key;
            }
        }
        return $this;
    }

    public function printCallstack($return = false)
    {
        $text = '';
        foreach ($this->_callstack as $index => $item) {
            $data = isset($this->_callstackValue[$index]) ? $this->_callstackValue[$index] : '?';
            $data = is_scalar($data) ? strval($data) : json_encode($data,JSON_UNESCAPED_UNICODE);
            $text .=  str_repeat('  ', $this->_callstackLevel[$index]) .'|-'. $item . ' ('.$data.')'."\n";
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
                if($v === null){
                    $v = 'nil';
                }
                $this->fields[$k] = $v;
            }
        } else {
            if($value === null){
                $value = 'nil';
            }
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
        if (is_a($interceptor,InterceptorInterface::class)) {
            $this->interceptors[] = $interceptor;
        }
        return $this;
    }

    public function getCallstack()
    {
        return $this->_callstack;
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
     * 如果key重置context
     * 或者置顶前缀的运算结果
     * @param bool $key 要重置的 key的前缀
     * @return array
     */
    public function reset()
    {
       $this->fields = [];
       foreach($this->interceptors as $inter){
            $inter->reset();
       }
       $this->callstack = [];
       $this->depends = [];
        return $this;
    }

    public function keyExist($key)
    {
        if($this->fieldExist($key))
        {
            return  true;
        }else{
            return isset($this->map[$key]);
        }
    }

    public function fieldGet($key){
        return $this->fieldExist($key)? $this->fields[$key] : null;
    }

    public function fieldExist($key){
        return isset($this->fields[$key]);
    }

    public function fieldSet($key,$value){
        $this->set($key,$value);
        return $this;
    }

    public function fieldRemove($key){
        unset($this->fields[$key]);
        return $this;
    }

    /**
     * 移除缓存中的字段
     * @param $keyPrefix
     * @return $this
     */
    public function fieldClear($keyPrefix)
    {
        $len = strlen($keyPrefix);
        foreach ($this->fields as $k => $v) {
            $prefix = substr($k, 0, $len);
            if ($prefix == $keyPrefix) {
                unset($this->fields[$k]);
            }
        }
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
     * 清除依赖
     * @param $key
     * @return  $this
     */
    private function clearDepends($key)
    {
        if (isset($this->depends[$key])) {
            foreach ($this->depends[$key] as $child) {
                unset($this->fields[$child]);
                //echo 'unset- '.$child."\n";
                $this->clearDepends($child);
            }
        }
    }

    /**
     * 读取多个属性以及部分meta
     * @param $keys
     * @return array
     */
    public function fetchs($keys , $valueOnly=true)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $valueOnly ?  $this->fetch($key) : [
                'label' => $this->label($key),
                'value' => $this->fetch($key),
                'meta' => $this->meta($key)
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
        $key = trim($key);
        if (isset($this->metas[$key])) {
            return isset($this->metas[$key]['label']) ? $this->metas[$key]['label'] : $key;
        } else {
            return $key;
        }
    }

    private function _fetch($key,$default = null){
        $this->callstack = [];
        $this->_callstack = [];
        $this->_callstackLevel = [];
        $this->_callstackValue = [];

        $result= $this->get($key,$default);
        if($this->_debug){
            $calls = [];
            foreach($this->_callstack as $index=>$call)
            {
                $calls[] = [
                    'key'=>$call,
                    'level'=>isset($this->_callstackLevel[$index]) ? $this->_callstackLevel[$index]:0,
                    'data'=>isset($this->_callstackValue[$index]) ? $this->_callstackValue[$index]:''
                ];
            }
            $this->_calls[$key] = $calls;
        }
        return $result;
    }

    /**
     * 清除调用堆栈，并获取运算结果
     * 如果key是数组则获取一组结果
     * @param $key string|array
     * @return mixed|null
     */
    public function fetch($key,$default = null)
    {
        if(is_array($key)){
            $data = [];
            foreach($key as  $k){
                $def  = is_array($default) && isset($default[$k])? $default[$k] : null;
                $data[$k] = $this->_fetch($k,$def);
            }
        }else{
            $data = $this->_fetch($key,$default);
        }
        return $data;

    }

    /**
     * 获取第一次的调用堆栈调用堆栈
     * @param null $key
     * @return array|mixed
     */
    public function getCalls($key = null)
    {
        return isset($this->_calls[$key])? $this->_calls[$key] : [];
    }

    public function printCalls($key = null,$return = false){
        $calls = $this->getCalls($key);
        $text = "";
        foreach($calls as $call){
            if(is_array($call['data']) || is_object($call['data'])){
                $data = json_encode($call['data'],JSON_UNESCAPED_UNICODE);
            }else{
                $data = $call['data'];
            }
            $repeatStr = $call['level'] > 0 ? str_repeat('--', $call['level']) : '';
            $text .= "|-" . $repeatStr . $call['key'] .'='.$data. "\n";
        }
        if($return){
            return $text;
        }else{
            echo $text;
        }
    }

    /**
     * 获取一组数据,只能获取reg或者set的数据
     * 拦截器载入的数据无法进行自动载入 请使用get或者fetch获取
     * @param $prefix
     * @return array
     */
    public function fetchAll($prefix){
        $len = strlen($prefix);

        $data =[];
        $fetched = [];

        foreach($this->fields as $key=>$value){
            if(strpos($key , $prefix) === 0){
                $fetched[$key] = 1;
                $subKey = substr($key,$len+1);
                if($subKey!='') {
                    $data[$subKey] = $value;
                }
            }
        }

        foreach($this->map as $key=>$value){
            if(isset($fetched[$key])){
                continue;
            }
            if(strpos($key , $prefix) === 0){
                //$fetched[$key] = 1;
                $subKey = substr($key,$len+1);
                if($subKey!=''){
                    $data[$subKey] = $this->_fetch($key);
                }

            }
        }
        return $data;
    }

    /**
     * 按前缀获取为数组
     * @param $prefix
     * @return array
     */
    public function fetchArray($prefix)
    {
        $data = $this->fetchAll($prefix);
        $result = [];
        foreach($data as $key=>$value)
        {
            if($key =='') continue;

            $keys = explode('.',$key);
            $keyStr = '';
            for($i = 0,$count = count($keys);$i<$count;$i++)
            {
                $k1 = $keys[$i];
                $keyStr .= '[\''.$k1.'\']';
                if($i == $count -1 ){
                    eval('$result'.$keyStr.'= $value;');
                }else{
                    $isset = eval('return isset($result'.$keyStr.') && is_array($result'.$keyStr.');');
                    if(!$isset){
                        eval('$result'.$keyStr.' = [];');
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 获取注册公式的说明
     * @param $key
     * @return mixed
     */
    public function meta($key)
    {
        $key = trim($key);
        if (isset($this->metas[$key])) {
            return $this->metas[$key];
        } else {
            return [];
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