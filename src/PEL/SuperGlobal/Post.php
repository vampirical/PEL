<?php

namespace PEL\SuperGlobal;

/**
 * PEL SuperGlobal Post
 *
 * @package PEL
 */


/**
 * PEL SuperGlobal Post
 *
 * @package PEL
 */
class Post extends Wrapper
{
	public function __construct(  )
	{
		parent::__construct();

		$this->link($_POST);

		if (get_magic_quotes_gpc()) {
			$this->stripSlashes();
		}
	}
}

?>
