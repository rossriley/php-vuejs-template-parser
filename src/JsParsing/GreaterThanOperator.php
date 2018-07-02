<?php

namespace WMDE\VueJsTemplating\JsParsing;

class GreaterThanOperator implements ParsedExpression {

    protected $left;
    protected $right;

    public function __construct($left, $right)
    {
        $this->left = $left;
        $this->right = $right;
	}

	/**
	 * @param array $data
	 *
	 * @return bool
	 */
	public function evaluate( array $data ): bool
    {
        return $this->left->evaluate($data) > $this->right->evaluate($data);
	}

}
