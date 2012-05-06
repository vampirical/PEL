<?php

namespace PEL;

/**
 * PEL Post
 *
 * @package PEL
 */


/**
 * PEL Post
 *
 * @package PEL
 */
class Post extends SuperGlobalWrapper
{
	public function __construct(  )
	{
		$this->link($_POST);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}
}

?>
