<?php

namespace PEL\SuperGlobal;

/**
 * PEL SuperGlobal Server
 *
 * @package PEL
 */


/**
 * PEL SuperGlobal Server
 *
 * @package PEL
 */
class Server extends Wrapper
{
	public function __construct(  )
	{
		parent::__construct();

		$this->link($_SERVER);
	}
}

?>
