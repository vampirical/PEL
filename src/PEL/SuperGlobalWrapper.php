<?php

namespace PEL;

/**
 * PEL Super Global Wrapper
 *
 * @package PEL
 */


/**
 * PEL Super Global Wrapper
 *
 * @package PEL
 */
class SuperGlobalWrapper extends Data
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
