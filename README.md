PHP Extended Library
=========================

Composer
-------------------------

{
	"require": {
		"pel/pel": "*"
	}
}

PHAR
-------------------------

To create a phar archive of this repo, run:
make

You can then copy the phar archive to your own project and include it like so:
include 'phar://path/to/PEL.phar';