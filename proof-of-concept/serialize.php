#!/usr/bin/env php
<?php
// PHP >= 5.4
// some ideas from:
// http://www.htmlist.com/development
//          /extending-php-5-3-closures-with-serialization-and-reflection/

function hello($world) {
    return "hello world\n";
}

// need class that:
// given a name, extracts function code and arguments list
// serializes both
// is able to unserialize this struct and return a closed function
// has static method that accepts a closed function and arguments to execute it

class FunctionMarshal implements Serializable {
    private $func_name;
    private $func_data;
    
    public function __construct($func_name=null) {
        if(!is_null($func_name)) {
            $this->func_name = $func_name;
            $this->_get_func_data();
        }
    }

    private function _get_func_data() {
        $func = new ReflectionFunction($this->func_name);

        $args = array();
        foreach ($func->getParameters() as $arg) {
            $args[] = $arg->name;
        }

        $fp = new SplFileObject($func->getFileName());
        $fp->seek($func->getStartLine());
        $body = '';
        while ($fp->key() < $func->getEndLine()) {
            $body .= $fp->current();
            $fp->next();
        }
        $body = rtrim($body, "}"." \t\n\r\0\x0b");

        $this->func_data = array('name'=>$this->func_name,
                                 'args'=>$args, 'body'=>$body);
    }

    public function serialize() {
        return serialize($this->func_data);
    }

    public function unserialize($data) {
        $this->func_data = unserialize($data);
        $this->func_name = $this->func_data['name'];
    }

    public function make() {
        $arglist = implode(',', array_map(function($i) { return '$'.$i; },
                                          $this->func_data['args']));
        return create_function($arglist, $this->func_data['body']);
    }

    public static function pack($func_name) {
        return serialize(new FunctionMarshal($func_name));
    }
    
    public static function unpack($data) {
        $fm = unserialize($data);
        return $fm->make();
    }
}

$serialized = FunctionMarshal::pack('hello');

$result = FunctionMarshal::unpack($serialized);

echo $result('hello');

