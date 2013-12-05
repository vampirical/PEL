PEL (PHP Extended Library)
=========================

TODO Description

Requirements
====================

Requires PHP 5.3, 5.3.8+ recommended.

Installation
====================

Composer
---------------
Require PEL in your composer.json file.

    {
	    "require": {
		    "pel/pel": "dev-master"
	    }
    }

Tell composer to install packages.

    composer install

or

	php composer.phar install

Reference composer's autoload.

    require_once 'vendor/autoload.php';

Phar
---------------

To create a phar archive of this repo, run:

    make

You can then copy the phar archive to your own project and include it like so:

    require_once 'PEL.phar';

TODO Link latest phars for each major version.