<?php

namespace PEL\Tests;

require_once __DIR__ .'/../src/PEL.php';

class ArrayUtil extends \PHPUnit_Framework_TestCase
{
	/**
	 * Summary
	 *
	 * @dataProvider keyByValuesProvider
	 */
	public function testKeyByValues($input, $keyBy, $output)
	{
		$this->assertEquals(\PEL\ArrayUtil::keyByValues($input, $keyBy), $output);
	}

	public function keyByValuesProvider()
	{
		return array(
			array( // Single key. 2 and 1
				array( // Input
					array(
						'one' => 'pudding',
						'two' => 'bacon',
						'three' => 'cheese'
					),
					array(
						'one' => 'pudding',
						'two' => 'foo',
						'three' => 'numbers'
					),
					array(
						'one' => 'red',
						'two' => 'green',
						'three' => 'blue'
					)
				),
				array( // Key By
					'one'
				),
				array( // Output
					'pudding' => array(
						array(
							'one' => 'pudding',
							'two' => 'bacon',
							'three' => 'cheese'
						),
						array(
							'one' => 'pudding',
							'two' => 'foo',
							'three' => 'numbers'
						)
					),
					'red' => array(
						array(
							'one' => 'red',
							'two' => 'green',
							'three' => 'blue'
						)
					)
				)
			),

			array( // Two key. 2/1 and 1/1
				array( // Input
					array(
						'one' => 'pudding',
						'two' => 'bacon',
						'three' => 'cheese'
					),
					array(
						'one' => 'pudding',
						'two' => 'foo',
						'three' => 'numbers'
					),
					array(
						'one' => 'red',
						'two' => 'green',
						'three' => 'blue'
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					'pudding' => array(
						'bacon' => array(
							array(
								'one' => 'pudding',
								'two' => 'bacon',
								'three' => 'cheese'
							)
						),
						'foo' => array(
							array(
								'one' => 'pudding',
								'two' => 'foo',
								'three' => 'numbers'
							)
						)
					),
					'red' => array(
						'green' => array(
							array(
								'one' => 'red',
								'two' => 'green',
								'three' => 'blue'
							)
						)
					)
				)
			),

			array( // Two key. 1/1, 1/1 and 1/1
				array( // Input
					array(
						'one' => 'pudding',
						'two' => 'bacon',
						'three' => 'cheese'
					),
					array(
						'one' => 'not pudding',
						'two' => 'bacon',
						'three' => 'numbers'
					),
					array(
						'one' => 'red',
						'two' => 'green',
						'three' => 'blue'
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					'pudding' => array(
						'bacon' => array(
							array(
								'one' => 'pudding',
								'two' => 'bacon',
								'three' => 'cheese'
							)
						)
					),
					'not pudding' => array(
						'bacon' => array(
							array(
								'one' => 'not pudding',
								'two' => 'bacon',
								'three' => 'numbers'
							)
						)
					),
					'red' => array(
						'green' => array(
							array(
								'one' => 'red',
								'two' => 'green',
								'three' => 'blue'
							)
						)
					)
				)
			),

			array( // Two key, exclude non-matching. 1/2
				array( // Input
					array(
						'one' => 'pudding',
						'two' => 'bacon',
						'three' => 'cheese'
					),
					array(
						'one' => 'pudding',
						'two' => 'foo',
						'three' => 'numbers'
					),
					array(
						'one' => 'red',
						'three' => 'blue'
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					'pudding' => array(
						'bacon' => array(
							array(
								'one' => 'pudding',
								'two' => 'bacon',
								'three' => 'cheese'
							)
						),
						'foo' => array(
							array(
								'one' => 'pudding',
								'two' => 'foo',
								'three' => 'numbers'
							)
						)
					)
				)
			)
		);
	}
}

?>
