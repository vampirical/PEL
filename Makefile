.PHONY: build all clean

build:
	php --define 'phar.readonly=0' ./create-phar.php

clean:
	rm -rf ./*.phar*
