<?php

namespace PEL\Storage;

class Memcache extends Provider
{
	protected $m;

	public function __construct($servers = null) {
		if (class_exists('Memcached', false)) {
			$this->m = new Memcached();
			$this->m->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
			if ($servers) $this->addServers($servers);
		}
	}

	public function addServers($servers) {
		if ($this->m) {
			return $this->m->addServers($servers);
		}
		return false;
	}

	public function addServer($host, $port = null, $weight = null) {
		if ($this->m) {
			if (!$port) {
				$port = 11211;
			}
			return $this->m->addServer($host, $port, $weight);
		}
		return false;
	}

	public function exists($key) {
		$this->m->get($key);
		return ($m->getResultCode() == Memcached::RES_SUCCESS);
	}

	public function set($key, $value, $expiration = null) {
		if ($this->m) {
			return $this->m->set($key, $value, $expiration);
		}
		return false;
	}

	public function setFile($key, $file, $expiration = null) {
		return $this->set($key, file_get_contents($file), $expiration);
	}

	public function get($key) {
		if ($this->m) {
			$value = $this->m->get($key);
			if ($this->m->getResultCode() == Memcached::RES_NOTFOUND) {
				return null;
			}
			return $value;
		}
		return null;
	}

	public function getInfo($key) {
		return null;
	}

	public function delete($key) {
		if ($this->m) {
			return $this->m->delete($key);
		}
		return false;
	}
}

?>
