<?php

namespace WMDE\VueJsTemplating\JsParsing;

class AdditionOperator implements ParsedExpression {

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
     * @return numeric
     */
	public function evaluate( array $data )
    {
        return $this->left->evaluate($data) + $this->right->evaluate($data);
	}

}
