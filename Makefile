.PHONY: build all clean

build:
	php --define 'phar.readonly=0' ./create_phar.php

clean:
	rm -rf ./*.phar*
