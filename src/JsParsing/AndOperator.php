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
        foreach ($this->expressions as $expr) {
            if (!$expr->evaluate($data)) {
                return false;
            }
        }

        return true;
	}

}
