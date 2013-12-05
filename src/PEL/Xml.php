<?php

namespace PEL;

class Xml
{
	/**
	 * Convert Simple XML to a nicely formatted XML string
	 *
	 * @param \SimpleXMLElement $sxml
	 *
	 * @throws Exception
	 * @return string
	 */
	public static function sxmlToXml(\SimpleXMLElement $sxml) {
		if (!($sxml instanceof \SimpleXMLElement)) {
			throw new Exception('Invalid argument $sxml, \SimpleXMLElement expected.');
		}

		$doc = new \DOMDocument();
		$doc->formatOutput = true;
		$simpleNode = dom_import_simplexml($sxml);
		$simpleNode = $doc->importNode($simpleNode, true);
		$simpleNode = $doc->appendChild($simpleNode);

		return $doc->saveXML();
	}

	/**
	 * Convert Simple XML to native types (array or string)
	 *
	 * @param	\SimpleXMLElement	$sxml
	 *
	 * @return array|string
	 */
	public static function sxmlToNative($sxml) {
		if (is_object($sxml) && ($sxml instanceof \SimpleXMLElement)) {
			$attributes = $sxml->attributes();
			if ($attributes) {
				foreach ($attributes as $k => $v) {
					if ($v) $a[$k] = (string) $v;
				}
			}
			$x = $sxml;
			$sxml = get_object_vars($sxml);
		}

		if (is_array($sxml)) {
			if (count($sxml) == 0) return (string) $x;
			foreach ($sxml as $key => $value) {
				$r[$key] = self::sxmlToNative($value);
			}
			if (isset($a)) $r['@attributes'] = $a;

			return $r;
		}

		return (string) $sxml;
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
	public static function sxmlMerge(\SimpleXMLElement $sxmlBase, \SimpleXMLElement $sxmlMerge) {
		if (!($sxmlBase instanceof \SimpleXMLElement)) {
			throw new Exception('Invalid argument $sxmlBase, \SimpleXMLElement expected.');
		}

		if (!($sxmlMerge instanceof \SimpleXMLElement)) {
			throw new Exception('Invalid argument $sxmlMerge, \SimpleXMLElement expected.');
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
