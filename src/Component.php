<?php

namespace WMDE\VueJsTemplating;

use DOMAttr;
use DOMCharacterData;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMText;
use Exception;
use InvalidArgumentException;
use LibXMLError;

use WMDE\VueJsTemplating\FilterExpressionParsing\FilterParser;
use WMDE\VueJsTemplating\JsParsing\BasicJsExpressionParser;
use WMDE\VueJsTemplating\JsParsing\CachingExpressionParser;

class Component {

	private $filterParser;

	/**
	 * @var string HTML
	 */
	private $template;

	/**
	 * @var callable[]
	 */
	private $filters = [];

	/**
	 * @var BasicJsExpressionParser
	 */
	private $expressionParser;

	/**
	 * @param string $template HTML
	 * @param callable[] $filters
	 */
	public function __construct( $template, array $filters ) {
		$this->template = $template;
		$this->filters = $filters;
		$this->expressionParser = new CachingExpressionParser( new BasicJsExpressionParser() );
		$this->filterParser = new FilterParser();
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function render( array $data ) {
		$document = $this->parseHtml( $this->template );

		$rootNode = $this->getRootNode( $document );
		$this->handleNode( $rootNode, $data );

		return $document->saveHTML( $rootNode );
	}

	/**
	 * @param string $template HTML
	 *
	 * @return DOMDocument
	 */
	private function parseHtml( $template ) {
		$entityLoaderDisabled = libxml_disable_entity_loader( true );
		$internalErrors = libxml_use_internal_errors( true );
		$document = new DOMDocument();

        if ( !$document->loadHTML( '<?xml encoding="utf-8" ?>' . $template ) ) {
            throw new InvalidArgumentException('Could not parse supplied template. Check it is valid HTML');
		}

		/** @var LibXMLError[] $errors */
		$errors = libxml_get_errors();
		libxml_clear_errors();

		// Restore previous state
		libxml_use_internal_errors( $internalErrors );
		libxml_disable_entity_loader( $entityLoaderDisabled );

		foreach ( $errors as $error ) {
			//TODO html5 tags can fail parsing
			//TODO Throw an exception
		}

		return $document;
	}

	/**
	 * @param DOMDocument $document
	 *
	 * @return DOMElement
	 * @throws Exception
	 */
	private function getRootNode( DOMDocument $document ) {
		$rootNodes = iterator_to_array( $document->documentElement->childNodes->item( 0 )->childNodes );

		if ( count( $rootNodes ) > 1 ) {
			throw new Exception( 'Template should have only one root node' );
		}

		return $rootNodes[0];
	}

	/**
	 * @param DOMNode $node
	 * @param array $data
	 */
	private function handleNode( DOMNode $node, array $data ) {
		$this->replaceMustacheVariables( $node, $data );

		if ( !$this->isTextNode( $node ) ) {
			$this->stripEventHandlers( $node );
			$this->handleFor( $node, $data );
			$this->handleRawHtml( $node, $data );

			if ( !$this->isRemovedFromTheDom( $node ) ) {
                $this->replaceShowWithIf($node->childNodes);
				$this->handleAttributeBinding( $node, $data );
				$this->handleIf( $node->childNodes, $data );

				foreach ( iterator_to_array( $node->childNodes ) as $childNode ) {
					$this->handleNode( $childNode, $data );
				}
			}
		}
	}

	private function stripEventHandlers( DOMNode $node ) {
		if ( $this->isTextNode( $node ) ) {
			return;
		}
		/** @var DOMAttr $attribute */
		foreach ( $node->attributes as $attribute ) {
			if ( strpos( $attribute->name, 'v-on:' ) === 0 ) {
				$node->removeAttribute( $attribute->name );
			}
		}
	}

	/**
	 * @param DOMNode $node
	 * @param array $data
	 */
	private function replaceMustacheVariables( DOMNode $node, array $data ) {
		if ( $node instanceof DOMText ) {
			$text = $node->wholeText;

			$regex = '/\{\{(?P<expression>.*?)\}\}/xs';
			preg_match_all( $regex, $text, $matches );

			foreach ( $matches['expression'] as $index => $expression ) {
				$value = $this->filterParser
                    ->setCurrentEnvironment($this)
                    ->setCurrentContext($data)
                    ->parse( $expression )
					->toExpression( $this->expressionParser, $this->filters )
					->evaluate( $data );

				$text = str_replace( $matches[0][$index], $value, $text );
			}

            if ( $text !== $node->wholeText ) {

			    // This step ensures that we get valid XML to add to our fragment
                $tmpDom = new DOMDocument();
                $xml = trim(str_replace('&', '&amp;', $text));

                if (strlen($xml)>0) {
                    $xml = sprintf('<?xml encoding="utf-8" ?><span>%s</span>', $xml);
                    libxml_use_internal_errors(true);
                    $tmpDom->loadHTML($xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS);
                    $xml = $tmpDom->saveXML($tmpDom->documentElement);
                    libxml_clear_errors();
                    $xml = substr($xml, 6);
                    $xml = substr($xml, 0, -7);
                }

                // Now we have it we can insert our fragment into the main dom
                $newNode = $node->ownerDocument->createDocumentFragment( );
                $newNode->appendXML($xml);
                $node->parentNode->replaceChild( $newNode, $node );
            }
		}
	}

    /**
     * For the purposes of a template parser we can simply re-map show blocks to if blocks
     * @param DOMNodeList $nodes
     */
    private function replaceShowWithIf(DOMNodeList $nodes): void
    {
        foreach ($nodes as $node) {
            if ( $this->isTextNode( $node ) ) {
                continue;
            }

            /** @var DOMElement $node */
            if ($node->hasAttribute('v-show')) {
                $node->setAttribute('v-if', $node->getAttribute('v-show'));
                $node->removeAttribute('v-show');
            }
        }
    }

	private function handleAttributeBinding( DOMElement $node, array $data ) {
		/** @var DOMAttr $attribute */
		foreach ( iterator_to_array( $node->attributes ) as $attribute ) {
			if ( !preg_match( '/^:[\-\_\w]+$/', $attribute->name ) ) {
				continue;
			}

			$value = $this->filterParser->setCurrentEnvironment($this)
                ->setCurrentContext($data)
                ->parse( $attribute->value )
				->toExpression( $this->expressionParser, $this->filters )
				->evaluate( $data );

			$name = substr( $attribute->name, 1 );
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$node->setAttribute( $name, $name );
				}
			} elseif(is_array($value)) {
			    if ($name === 'style') {
			        $styles = [];
                    foreach ($value as $prop => $setting) {
                        if ($setting) {
                            $prop = strtolower(preg_replace('/([A-Z])/', '-$1', $prop));
                            $styles[]= sprintf('%s:%s', $prop, $setting);
                        }
                    }
                    $node->setAttribute($name, implode(';', $styles));
                } elseif ($name === 'class') {
			        $classes = [];
                    foreach ($value as $className => $setting) {
                        if ($setting) {
                            $classes[] = $className;
                        }
                    }
                    $node->setAttribute($name, implode(' ', $classes));
                }
            } else {
				$node->setAttribute( $name, $value );
			}
			$node->removeAttribute( $attribute->name );
		}
	}

	/**
	 * @param DOMNodeList $nodes
	 * @param array $data
	 */
	private function handleIf( DOMNodeList $nodes, array $data ) {
		// Iteration of iterator breaks if we try to remove items while iterating, so defer node
		// removing until finished iterating.
		$nodesToRemove = [];
		foreach ( $nodes as $node ) {
			if ( $this->isTextNode( $node ) ) {
				continue;
			}

			/** @var DOMElement $node */
			if ( $node->hasAttribute( 'v-if' ) ) {
				$conditionString = $node->getAttribute( 'v-if' );
				$node->removeAttribute( 'v-if' );
				$condition = $this->evaluateExpression( $conditionString, $data );

				if ( !$condition ) {
					$nodesToRemove[] = $node;
				}

				$previousIfCondition = $condition;
			} elseif ( $node->hasAttribute( 'v-else' ) ) {
				$node->removeAttribute( 'v-else' );

				if ( $previousIfCondition ) {
					$nodesToRemove[] = $node;
				}
			}
		}

		foreach ( $nodesToRemove as $node ) {
			$this->removeNode( $node );
		}
	}

	private function handleFor( DOMNode $node, array $data ) {
		if ( $this->isTextNode( $node ) ) {
			return;
		}

		/** @var DOMElement $node */
		if ( $node->hasAttribute( 'v-for' ) ) {
			[$itemName, $listName] = explode( ' in ', $node->getAttribute( 'v-for' ) );
			if (strpos($itemName, ',') !== false) {
			    [$keyName, $itemName] = explode(',', $itemName);
                $keyName = trim($keyName, ' (');
                $itemName = trim($itemName, ') ');
            }
			$node->removeAttribute( 'v-for' );

            $items = $this->evaluateExpression($listName, $data) ?? [];
            if (!is_iterable($items)) {
                $items = [];
            }
			foreach ( $items as $keyIndex => $item ) {
			    $keyName = $keyName ?? 'key';
				$newNode = $node->cloneNode( true );
				$node->parentNode->insertBefore( $newNode, $node );
				$this->handleNode( $newNode, array_merge( $data, [ $itemName => $item, $keyName => $keyIndex ] ) );
			}

			$this->removeNode( $node );
		}
	}

	private function appendHTML( DOMNode $parent, $source ) {
		$tmpDoc = $this->parseHtml( $source );
		foreach ( $tmpDoc->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {
			$node = $parent->ownerDocument->importNode( $node, true );
			$parent->appendChild( $node );
		}
	}

	private function handleRawHtml( DOMNode $node, array $data ) {
		if ( $this->isTextNode( $node ) ) {
			return;
		}

		/** @var DOMElement $node */
		if ( $node->hasAttribute( 'v-html' ) ) {
			$variableName = $node->getAttribute( 'v-html' );
			$node->removeAttribute( 'v-html' );

			$newNode = $node->cloneNode( true );

			$this->appendHTML( $newNode, $data[$variableName] );

			$node->parentNode->replaceChild( $newNode, $node );
		}
	}

	/**
	 * @param string $expression
	 * @param array $data
	 *
	 * @return bool
	 */
	private function evaluateExpression( $expression, array $data ) {
		return $this->expressionParser->parse( $expression )->evaluate( $data );
	}

	private function removeNode( DOMElement $node ) {
		$node->parentNode->removeChild( $node );
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return bool
	 */
	private function isTextNode( DOMNode $node ) {
		return $node instanceof DOMCharacterData;
	}

	private function isRemovedFromTheDom( DOMNode $node ) {
		return $node->parentNode === null;
	}

    /**
     * @return callable[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

}
