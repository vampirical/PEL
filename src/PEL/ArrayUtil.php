<?php

namespace PEL;

class ArrayUtil
{
	/**
	 * Key by values
	 *
	 * Keys an array of arrays by values within the arrays.
	 * If a specified key is not present or has a null value, that array
	 * will be excluded from the response.
	 *
	 * If you only need to use a single key and are on PHP 5.5+, you may want to
	 * consider using array_column instead.
	 *
	 * @param array $arraySet
	 * @param array $keyBy Flat array of keys to use.
	 *
	 * @return array
	 */
	public static function keyByValues($arraySet, $keyBy)
	{
		$keyBy = array_values($keyBy);
		$keyByLength = count($keyBy);

		$keyedArray = array();

		// Pre-populate keys to avoid checking as we go, we'll trim afterwards.
		$keyValueItemMap = array();
		foreach ($keyBy as $key) {
			$keyValueItemMap[$key] = array();
		}
		foreach ($arraySet as $arrayIndex => $array) {
			foreach ($keyBy as $key) {
				if (!isset($array[$key])) {
					unset($arraySet[$arrayIndex]); // Save additional comparisons for non-matching items.
					continue;
				}
				$keyValue = $array[$key];
				$keyValueItemMap[$key][$keyValue][] = $arrayIndex;
			}
		}
		// Now that we're done indexing, trim down to just the keys with matches.
		foreach ($keyValueItemMap as $key => $keyData) {
			if (!$keyData) {
				unset($keyValueItemMap[$key]);
			}
		}

		// Flip our index around, $keyValueItemMap is replaced by $matchesByArrayIndex.
		$matchesByArrayIndex = array();
		foreach ($keyValueItemMap as $key => $keyData) {
			foreach ($keyData as $value => $arrayIndexes) {
				foreach ($arrayIndexes as $arrayIndex) {
					$matchesByArrayIndex[$arrayIndex][] = $value;
				}
			}
		}
		unset($keyValueItemMap);

		foreach ($matchesByArrayIndex as $arrayIndex => $matches) {
			// Throw out partial matches as we go.
			if (count($matches) !== $keyByLength) {
				unset($matchesByArrayIndex[$arrayIndex]);
				continue;
			}

			$temp = array();
			$matchesMaxIndex = count($matches) - 1;
			for ($i = $matchesMaxIndex; $i >= 0; $i--) {
				$matchValue = $matches[$i];
				if ($i === $matchesMaxIndex) {
					$temp[$matchValue] = array($arraySet[$arrayIndex]);
				} else {
					$oldTemp = $temp;
					$temp = array(
						$matchValue => $oldTemp
					);
					unset($oldTemp);
				}
			}

			$keyedArray = array_merge_recursive($keyedArray, $temp);
		}

		return $keyedArray;
	}

	/**
	 * Array values to associative
	 *
	 * Convert an array of values to an associative array.
	 * The first element will be a key, the second a value and so on.
	 *
	 * @param	array $input
	 *
	 * @return array
	 */
	public static function valuesToAssoc($input) {
		$output = array();

		$inputValues = array_values($input);
		for ($i = 0; $i < count($input); $i++) {
			if ($i % 2 === 0) {
				$output[$inputValues[$i]] = null;
			} else {
				$output[$inputValues[$i - 1]] = $inputValues[$i];
			}
		}

		return $output;
	}
}

?>
