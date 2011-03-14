<?php

namespace PEL;

/**
 * PEL Request
 *
 * @package PEL
 */


/**
 * PEL Request
 *
 * @package PEL
 */
class Request extends SuperGlobalWrapper
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
