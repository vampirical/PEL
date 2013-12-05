#!/usr/bin/env php
<?php

$file = __DIR__ . '/PEL.phar';

@unlink($file . '.tar.gz');

$phar = new Phar($file);
$phar->setStub($phar->createDefaultStub('PEL.php'));
$phar->buildFromDirectory( __DIR__ . '/src',  '#\.php$#');

?>
