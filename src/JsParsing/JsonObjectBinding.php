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
	    $result = '';
        $bindings = explode(trim($this->string, '{}'), ',');
        foreach ($bindings as $item) {
            list($left, $right) = explode(':', $item);
            $result .= sprintf('%s : $s;', $left, $this->parser->parse($right));
        }

        return $result;
	}

}
