<?php

namespace PEL\Tests\Db;

use \PEL\Db;

require_once dirname(dirname(__DIR__)) .'/src/PEL.php';

class ExampleRecord extends Db\Record
{
	protected static $table = 'example';
	protected static $fields = array(
		'id',
		'value'
	);
	protected static $primaryKeyFields = array('id');
}

?>
