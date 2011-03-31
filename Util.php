<?php

namespace PEL;

class Util
{
	/**
	 * Append trailing slash if not already present
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function slashify($string)
	{
		$lastChar = substr($string, -1);
		if ($lastChar != '/' && $lastChar != '\\') {
			$string .= '/';
		}

		return $string;
	}

	/**
	* Convert an array of values to an associative array
	* The first element will be a key, the second a value and so on
	*
	* @param array $input
	*
	* @return array
	*/
	public static function arrayValuesToAssoc($input)
	{
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

	/**
	 * Convert Simple XML to a nicely formatted XML string
	 *
	 * @param \SimpleXMLElement $sxml
	 *
	 * @throws Exception
	 * @return string
	 */
	public static function sxmlToXml(\SimpleXMLElement $sxml)
	{
		if (!($sxml instanceof \SimpleXMLElement)) {
			throw new Exception('Invalid argument $sxml, \SimpleXMLElement expected.');
		}

		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$simpleNode = dom_import_simplexml($sxml);
		$simpleNode = $doc->importNode($simpleNode, true);
		$simpleNode = $doc->appendChild($simpleNode);

		return $doc->saveXML();
	}
	
	/**
	 * Merge two \SimpleXMLElements
	 *
	 * @param \SimpleXMLElement $sxmlBase
	 * @param \SimpleXMLElement $sxmlMerge
	 *
	 * @throws Exception
	 * @return \SimpleXMLElement
	 */
	public static function sxmlMerge(\SimpleXMLElement $sxmlBase, \SimpleXMLElement $sxmlMerge)
	{
		if (!($sxmlBase instanceof \SimpleXMLElement)) {
			throw new TPP_Exception('Invalid argument $sxmlBase, \SimpleXMLElement expected.');
		}

		if (!($sxmlMerge instanceof \SimpleXMLElement)) {
			throw new TPP_Exception('Invalid argument $sxmlMerge, \SimpleXMLElement expected.');
		}

		$domBase = new \DomDocument();
		$domMerge = new \DomDocument();
		$domBase->loadXML($sxmlBase->asXML());
		$domMerge->loadXML($sxmlMerge->asXML());

		$xpath = new \domXPath($domMerge);
		$xpathQuery = $xpath->query('/*/*');
		for ($i = 0; $i < $xpathQuery->length; $i++) {
			$domBase->documentElement->appendChild($domBase->importNode($xpathQuery->item($i), true));
		}
		$sxmlBase = simplexml_import_dom($domBase);

		foreach ($sxmlMerge->attributes() as $attributeName => $attributeValue) {
			$sxmlBase[$attributeName] = $attributeValue;
		}

		return $sxmlBase;
	}

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

		$str = substr($str, 2);
		$len = strlen($str);
		$dec = '';
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

	public static function strlcs($str1, $str2) {
		$str1Len = strlen($str1);
		$str2Len = strlen($str2);
		$ret = array();
	 
		if($str1Len == 0 || $str2Len == 0)
			return $ret; //no similarities
	 
		$CSL = array(); //Common Sequence Length array
		$intLargestSize = 0;
	 
		//initialize the CSL array to assume there are no similarities
		for($i=0; $i<$str1Len; $i++){
			$CSL[$i] = array();
			for($j=0; $j<$str2Len; $j++){
				$CSL[$i][$j] = 0;
			}
		}
	 
		for($i=0; $i<$str1Len; $i++){
			for($j=0; $j<$str2Len; $j++){
				//check every combination of characters
				if( $str1[$i] == $str2[$j] ){
					//these are the same in both strings
					if($i == 0 || $j == 0)
						//it's the first character, so it's clearly only 1 character long
						$CSL[$i][$j] = 1; 
					else
						//it's one character longer than the string from the previous character
						$CSL[$i][$j] = $CSL[$i-1][$j-1] + 1; 
	 
					if( $CSL[$i][$j] > $intLargestSize ){
						//remember this as the largest
						$intLargestSize = $CSL[$i][$j]; 
						//wipe any previous results
						$ret = array();
						//and then fall through to remember this new value
					}
					if( $CSL[$i][$j] == $intLargestSize )
						//remember the largest string(s)
						$ret[] = substr($str1, $i-$intLargestSize+1, $intLargestSize);
				}
				//else, $CSL should be set to 0, which it was already initialized to
			}
		}
		//return the list of matches
		return $ret;
	}
}

?>
