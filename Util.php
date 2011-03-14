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
}

?>
