<?php

namespace WMDE\VueJsTemplating\JsParsing;

class VariableAccess implements ParsedExpression {

	/**
	 * @var string[]
	 */
	private $pathParts;

	public function __construct( array $pathParts ) {
		$this->pathParts = $pathParts;
	}

	/**
	 * @param array $data
	 *
	 * @return mixed
	 */
    public function evaluate( array $data ) {
        $value = $data;
        foreach ( $this->pathParts as $key ) {
            if ( !array_key_exists( $key, (array)$value ) ) {
                $value[$key] = '';
                return $value[$key];
            }
            $value = $value[$key];
        }
        return $value;
    }

}
