<?php

namespace WMDE\VueJsTemplating\JsParsing;

class BasicJsExpressionParser implements JsExpressionParser {

	/**
	 * @param string $expression
	 *
	 * @return ParsedExpression
	 */
	public function parse( $expression ) {
		$expression = $this->normalizeExpression( $expression );

        if (strpos($expression, '&&') !== false) {
            $parts = explode('&&', $expression);
            array_walk($parts, function(&$part) {
                $part = $this->parse(trim($part));
            });

            return new AndOperator(...$parts);
        }

        if (strpos($expression, '||') !== false) {
            $parts = explode('||', $expression);
            array_walk($parts, function(&$part) {
                $part = $this->parse(trim($part));
            });

            return new OrOperator(...$parts);
        }

        if ( strpos( $expression, '!' ) === 0 ) { // ! operator application
            return new NegationOperator( $this->parse( substr( $expression, 1 ) ) );
        } elseif (strpos($expression, '!=') !== false ){
            $parts = explode('!=', $expression);
            return new NegationOperator(new ComparisonOperator($this->parse(trim($parts[0])), $this->parse(trim($parts[1]))));
        } elseif ( strpos($expression, '==') !== false ) {
            $parts = explode('==', $expression);
            return new ComparisonOperator($this->parse(trim($parts[0])), $this->parse(trim($parts[1] )));
        } elseif ( strpos( $expression, "'" ) === 0 ) {
            return new StringLiteral( substr( $expression, 1, strlen( $expression ) - 2 ) );
        } elseif (is_numeric( $expression )) {
            return new StringLiteral($expression);
        } else {
            $parts = explode( '.', $expression );
            return new VariableAccess( $parts );
        }
	}

	/**
	 * @param string $expression
	 *
	 * @return string
	 */
	protected function normalizeExpression( $expression )
    {
	    if ($expression === "''") {
	        $expression = '';
        }

		return trim( $expression );
	}

}
