<?php

namespace WMDE\VueJsTemplating\JsParsing;

class JsonObjectBinding implements ParsedExpression {

    /**
     * @var string
     */
    private $string;
    /**
     * @var JsExpressionParser
     */
    private $parser;

    public function __construct( $string , JsExpressionParser $parser) {
        $this->string = $string;
        $this->parser = $parser;
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function evaluate( array $data ) {
        $result = [];
        $bindings = preg_split('/(?!\B"[^"]*),(?![^"]*"\B)/', trim($this->string, '{} '), -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
        foreach ($bindings as $item) {
            [$left, $right] =  preg_split('/(?!\B"[^"]*):(?![^"]*"\B)/', trim($item, ' '), -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
            $boundValue = $this->parser->parse(trim($right))->evaluate($data);
            $result[trim($left)] = $boundValue;
        }

        return $result;
    }

}
