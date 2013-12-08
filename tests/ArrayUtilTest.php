<?php

namespace PEL\Tests;

use \PEL\ArrayUtil;

require_once dirname(__DIR__) .'/src/PEL.php';

class ArrayUtilTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * Test Key By Values
	 *
	 * @dataProvider keyByValuesStringKeysProvider
	 */
	public function testKeyByValuesStringKeys($input, $keyBy, $output)
	{
		$keyed = ArrayUtil::keyByValues($input, $keyBy);
		$this->assertEquals($keyed, $output);
	}

	public function keyByValuesStringKeysProvider()
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

	/**
	 * Test Key By Values
	 *
	 * @dataProvider keyByValuesNumericKeysProvider
	 */
	public function testKeyByValuesNumericKeys($input, $keyBy, $output)
	{
		$keyed = ArrayUtil::keyByValues($input, $keyBy);
		$this->assertEquals($keyed, $output);
	}

	public function keyByValuesNumericKeysProvider()
	{
		// TODO Create new test data for numeric keys.
		return array(
			array( // Single key. 2 and 1
				array( // Input
					array(
						'one' => 11,
						'two' => 22,
						'three' => 33
					),
					array(
						'one' => 11,
						'two' => 44,
						'three' => 55
					),
					array(
						'one' => 66,
						'two' => 77,
						'three' => 88
					)
				),
				array( // Key By
					'one'
				),
				array( // Output
					11 => array(
						array(
							'one' => 11,
							'two' => 22,
							'three' => 33
						),
						array(
							'one' => 11,
							'two' => 44,
							'three' => 55
						)
					),
					66 => array(
						array(
							'one' => 66,
							'two' => 77,
							'three' => 88
						)
					)
				)
			),

			array( // Two key. 2/1 and 1/1
				array( // Input
					array(
						'one' => 11,
						'two' => 22,
						'three' => 33
					),
					array(
						'one' => 11,
						'two' => 44,
						'three' => 55
					),
					array(
						'one' => 66,
						'two' => 77,
						'three' => 88
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					11 => array(
						22 => array(
							array(
								'one' => 11,
								'two' => 22,
								'three' => 33
							)
						),
						44 => array(
							array(
								'one' => 11,
								'two' => 44,
								'three' => 55
							)
						)
					),
					66 => array(
						77 => array(
							array(
								'one' => 66,
								'two' => 77,
								'three' => 88
							)
						)
					)
				)
			),

			array( // Two key. 1/1, 1/1 and 1/1
				array( // Input
					array(
						'one' => 11,
						'two' => 22,
						'three' => 33
					),
					array(
						'one' => 99,
						'two' => 22,
						'three' => 55
					),
					array(
						'one' => 66,
						'two' => 77,
						'three' => 88
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					11 => array(
						22 => array(
							array(
								'one' => 11,
								'two' => 22,
								'three' => 33
							)
						)
					),
					99 => array(
						22 => array(
							array(
								'one' => 99,
								'two' => 22,
								'three' => 55
							)
						)
					),
					66 => array(
						77 => array(
							array(
								'one' => 66,
								'two' => 77,
								'three' => 88
							)
						)
					)
				)
			),

			array( // Two key, exclude non-matching. 1/2
				array( // Input
					array(
						'one' => 11,
						'two' => 22,
						'three' => 33
					),
					array(
						'one' => 11,
						'two' => 44,
						'three' => 55
					),
					array(
						'one' => 66,
						'three' => 88
					)
				),
				array( // Key By
					'one',
					'two'
				),
				array( // Output
					11 => array(
						22 => array(
							array(
								'one' => 11,
								'two' => 22,
								'three' => 33
							)
						),
						44 => array(
							array(
								'one' => 11,
								'two' => 44,
								'three' => 55
							)
						)
					)
				)
			)
		);
	}
}

?>
