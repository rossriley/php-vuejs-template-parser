<?php

namespace WMDE\VueJsTemplating\JsParsing;

class AndOperator implements ParsedExpression {

    protected $expressions;

    public function __construct(...$expressions)
    {
        $this->expressions = $expressions;
	}

	/**
	 * @param array $data
	 *
	 * @return bool
	 */
	public function evaluate( array $data ): bool
    {
        $left = true;
        foreach ($this->expressions as $expr) {
            if (!($left && ($left = $expr->evaluate($data)))) {
                return false;
            }
        }

        return $left;
	}

}
