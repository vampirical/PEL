<?php

namespace PEL;

/**
 * PEL Server
 *
 * @package PEL
 */


/**
 * PEL Server
 *
 * @package PEL
 */
class Server extends SuperGlobalWrapper
{
	public function __construct(  )
	{
		$this->link($_SERVER);
	}
}

?>
