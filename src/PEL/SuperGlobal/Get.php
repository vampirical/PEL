<?php

namespace PEL\SuperGlobal;

/**
 * PEL SuperGlobal Get
 *
 * @package PEL
 */


/**
 * PEL SuperGlobal Get
 *
 * @package PEL
 */
class Get extends Wrapper
{
	public function __construct()
	{
		parent::__construct();

		$this->link($_GET);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}
}

?>
