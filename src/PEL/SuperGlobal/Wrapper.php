<?php

namespace PEL\SuperGlobal;

/**
 * PEL SuperGlobal Wrapper
 *
 * @package PEL
 */


/**
 * PEL SuperGlobal Wrapper
 *
 * @package PEL
 */
class Wrapper extends \PEL\Data
{
	/**
	 * Link to super global
	 *
	 * @param array $superGlobal
	 */
	public function link(&$superGlobal)
	{
		$this->data =& $superGlobal;
	}
}

?>
