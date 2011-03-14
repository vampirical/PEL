<?php

namespace PEL;

/**
 * PEL Get
 *
 * @package PEL
 */


/**
 * PEL Get
 *
 * @package PEL
 */
class Get extends SuperGlobalWrapper
{
	public function __construct()
	{
		$this->link($_GET);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}
}

?>
