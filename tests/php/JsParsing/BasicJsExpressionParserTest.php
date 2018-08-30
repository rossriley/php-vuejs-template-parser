<?php

namespace WMDE\VueJsTemplating\Test\JsParsing;

use WMDE\VueJsTemplating\JsParsing\BasicJsExpressionParser;

class BasicJsExpressionParserTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @test
	 */
	public function canParseString() {
		$jsExpressionEvaluator = new BasicJsExpressionParser();

		$parsedExpression = $jsExpressionEvaluator->parse( "'some string'" );
		$result = $parsedExpression->evaluate( [] );

		$this->assertEquals( 'some string', $result );
	}

	/**
	 * @test
	 */
	public function canParsePropertyAccess() {
		$jsExpressionEvaluator = new BasicJsExpressionParser();

		$parsedExpression = $jsExpressionEvaluator->parse( "variable.property" );
		$result = $parsedExpression->evaluate( [ 'variable' => [ 'property' => 'some value' ] ] );

		$this->assertEquals( 'some value', $result );
	}

	/**
	 * @test
	 */
	public function canParseNegationOperator() {
		$jsExpressionEvaluator = new BasicJsExpressionParser();

		$negation = $jsExpressionEvaluator->parse( "!variable" );

		$this->assertEquals( true, $negation->evaluate( [ 'variable' => false ] ) );
		$this->assertEquals( false, $negation->evaluate( [ 'variable' => true ] ) );
	}

	/**
	 * @test
	 */
	public function ignoresTrailingAndLeadingSpaces() {
		$jsExpressionEvaluator = new BasicJsExpressionParser();

		$parsedExpression = $jsExpressionEvaluator->parse( " 'some string' " );
		$result = $parsedExpression->evaluate( [] );

		$this->assertEquals( 'some string', $result );
	}

	public function testAndOperator()
    {
        $jsExpressionEvaluator = new BasicJsExpressionParser();
        $parsedExpression = $jsExpressionEvaluator->parse( "true && false" );
        $result = $parsedExpression->evaluate( [] );

        $this->assertEquals( false, $result );


        $parsedExpression = $jsExpressionEvaluator->parse( "true && true" );
        $result = $parsedExpression->evaluate( [] );

        $this->assertEquals( true, $result );
    }

    public function testOROperator()
    {
        $jsExpressionEvaluator = new BasicJsExpressionParser();
        $parsedExpression = $jsExpressionEvaluator->parse( "true || false" );
        $result = $parsedExpression->evaluate( [] );

        $this->assertEquals( true, $result );


        $parsedExpression = $jsExpressionEvaluator->parse( "false || ''" );
        $result = $parsedExpression->evaluate( [] );

        $this->assertEquals( false, $result );
    }

}
