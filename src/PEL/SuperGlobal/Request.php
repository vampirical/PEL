<?php

namespace PEL\SuperGlobal;

/**
 * PEL SuperGlobal Request
 *
 * @package PEL
 */


/**
 * PEL SuperGlobal Request
 *
 * @package PEL
 */
class Request extends Wrapper
{
	protected $body;

	public function __construct()
	{
		$this->link($_REQUEST);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}

	public function getBody()
	{
		if (!$this->body) {
			$this->body = file_get_contents('php://input');
		}

		return $this->body;
	}
}

?>
