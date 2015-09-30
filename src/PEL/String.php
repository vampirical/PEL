<?php

namespace PEL;

class String
{
	/**
	 * Append trailing slash if not already present
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function slashify($string) {
		$lastChar = substr($string, -1);
		if ($lastChar != '/' && $lastChar != '\\') {
			$string .= '/';
		}

		return $string;
	}

	/**
	 * Strip slashes recursively
	 *
	 * @param	mixed $value
	 *
	 * @return mixed
	 */
	public static function stripSlashes($value)
	{
		if (is_array($value)) {
			$value = array_map(array(__CLASS__, 'stripSlashes'), $value);
		} else if (is_object($value)) {
			foreach ($value as &$subValue) {
				$subValue = self::stripSlashes($subValue);
			}
		} else {
			$value = stripslashes($value);
		}

		return $value;
	}

	/**
	* Camel case a string
	*
	* Camel case a string by uppercasing after whitespace and then removing whitespace.
	* Standard whitespace: space, form-feed, newline, carriage return, horizontal tab, and vertical tab
	* Default additionalWhitespace: hyphen (-), underscore (_)
	*
	* @param string $string
	* @param array  $additionalWhitespace Additional strings which should be treated as whitespace
	*
	* @return string
	*/
	public static function camelCase($string, $additionalWhitespace = array('-', '_')) {
		return preg_replace('/\s/', '', ucwords(str_replace($additionalWhitespace, ' ', $string)));
	}

	/**
	* Remove camel casing from a string
	*
	* Removing camel casing from a string by inserting a separator character before uppercased characters.
	* Default separatorCharacter: space ( )
	*
	* @param string       $string
	* @param string|array $separatorCharacter Defaults to a single space ' '
	*
	* @return string
	*/
	public static function removeCamelCase($string, $separatorCharacter = ' ') {
		$chars = str_split($string, 1);
		for ($i = 0, $l = count($chars); $i < $l; $i++) {
			$char = $chars[$i];
			if (ctype_upper($char)) {
				$replacementArray = array();
				if ($i > 0) {
					$replacementArray[] = $separatorCharacter;
				}
				$replacementArray[] = strtolower($char);
				array_splice($chars, $i, 1, $replacementArray);
				$i++;
			}
		}

		return implode('', $chars);
	}

	// Encoding

	/**
	 * RFC3986 compatible string encoder
	 *
	 * @param	string $string
	 *
	 * @return string
	 */
	public static function encodeRfc3986($string) {
		$string = rawurlencode($string);

		if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
			$string = str_replace('%7E', '~', $string);
		}

		return $string;
	}

	/**
	 * Convert string of UTF16 characters to UTF8
	 *
	 * @param	string $str UTF16 encoded string
	 *
	 * @return string UTF8 encoded string
	 */
	public static function utf16ToUtf8($str) {
		$c0 = ord($str[0]);
		$c1 = ord($str[1]);

		if ($c0 == 0xFE && $c1 == 0xFF) {
			$be = true;
		} else if ($c0 == 0xFF && $c1 == 0xFE) {
			$be = false;
		} else {
			return $str;
		}

		$dec = '';
		$str = substr($str, 2);
		$len = strlen($str);
		for ($i = 0; $i < $len; $i += 2) {
			$c = ($be) ? ord($str[$i]) << 8 | ord($str[$i + 1]) :
			ord($str[$i + 1]) << 8 | ord($str[$i]);
			if ($c >= 0x0001 && $c <= 0x007F) {
				$dec .= chr($c);
			} else if ($c > 0x07FF) {
				$dec .= chr(0xE0 | (($c >> 12) & 0x0F));
				$dec .= chr(0x80 | (($c >>  6) & 0x3F));
				$dec .= chr(0x80 | (($c >>  0) & 0x3F));
			} else {
				$dec .= chr(0xC0 | (($c >>  6) & 0x1F));
				$dec .= chr(0x80 | (($c >>  0) & 0x3F));
			}
		}

		return $dec;
	}

	// Base

	/**
	 * Convert a number to a base based on a set of characters
	 *
	 * @param	int|float $number
	 * @param string    $characterSet
	 *
	 * @return string
	 */
	public static function baseConvert($number, $characterSet) {
		$output = '';

		$base = strlen($characterSet);
		while ($number > 0) {
			$output = substr($characterSet, ($number % $base), 1) . $output;
			$number = floor($number / $base);
		}

		return $output;
	}

	/**
	 * Convert a number to base33 (lower hex excluding zero, i, and o)
	 *
	 * @param	int|float $number
	 *
	 * @return string
	 */
	public static function base33($number) {
		return self::baseConvert($number, '123456789abcdefghjklmnpqrstuvwxyz');
	}

	/**
	 * Convert a number to base36 (lower hex)
	 *
	 * @param	int|float $number
	 *
	 * @return string
	 */
	public static function base36($number) {
		return self::baseConvert($number, '0123456789abcdefghijklmnopqrstuvwxyz');
	}

	/**
	 * Convert a number to base62 (upper/lower hex)
	 *
	 * @param	int|float $number
	 *
	 * @return string
	 */
	public static function base62($number) {
		return self::baseConvert($number, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
	}

	/**
	 * Create a string of random base33 (lower hex, excluding zero, i, and o) characters
	 *
	 * @param	int $length
	 *
	 * @return string
	 */
	public static function randomBase33($length) {
		$result = '';

		while (strlen($result) < $length) {
			$nextChar = mt_rand(0, 32);
			$result .= self::base33($nextChar);
		}

		return $result;
	}

	/**
	 * Create a string of random base36 (lower hex) characters
	 *
	 * @param	int $length
	 *
	 * @return string
	 */
	public static function randomBase36($length) {
		$result = '';

		while (strlen($result) < $length) {
			$nextChar = mt_rand(0, 35);
			$result .= self::base36($nextChar);
		}

		return $result;
	}

	/**
	 * Create a string of random base62 (upper/lower hex) characters
	 *
	 * @param	int $length
	 *
	 * @return string
	 */
	public static function randomBase62($length) {
		$result = '';

		while (strlen($result) < $length) {
			$nextChar = mt_rand(0, 61); // 10 digits + 26 uppercase + 26 lowercase = 62 chars
			if (($nextChar >=10) && ($nextChar < 36)) { // uppercase letters
				$nextChar -= 10;
				$nextChar = chr($nextChar + 65); // ord('A') == 65
			} else if ($nextChar >= 36) { // lowercase letters
				$nextChar -= 36;
				$nextChar = chr($nextChar + 97); // ord('a') == 97
			} else { // 0-9
				$nextChar = chr($nextChar + 48); // ord('0') == 48
			}
			$result .= $nextChar;
		}

		return $result;
	}

	// Analysis

	/**
	 * Find the longest common strings shared by two strings
	 *
	 * @param	string $str1
	 * @param	string $str2
	 *
	 * @return array
	 */
	public static function longestCommon($str1, $str2) {
		$str1Len = strlen($str1);
		$str2Len = strlen($str2);
		$result = array();

		if ($str1Len == 0 || $str2Len == 0) {
			return $result;
		}

		$CSL = array(); // Common Sequence Length
		for ($i = 0; $i < $str1Len; $i++) {
			$CSL[$i] = array();
			for ($j = 0; $j < $str2Len; $j++) {
				$CSL[$i][$j] = 0;
			}
		}
		$intLargestSize = 0;

		for ($i = 0; $i < $str1Len; $i++) {
			for ($j = 0; $j < $str2Len; $j++) {
				if ($str1[$i] == $str2[$j]) {
					if ($i == 0 || $j == 0) {
						$CSL[$i][$j] = 1;
					} else {
						$CSL[$i][$j] = $CSL[$i-1][$j-1] + 1;
					}

					if ($CSL[$i][$j] > $intLargestSize) {
						$intLargestSize = $CSL[$i][$j];
						$result = array(); // Reset when there is a new larger size.
					}
					if ($CSL[$i][$j] == $intLargestSize) {
						$result[] = substr($str1, $i-$intLargestSize+1, $intLargestSize);
					}
				}
			}
		}

		return $result;
	}
}

?>
