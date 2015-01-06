<?php

namespace PEL;

class Storage
{
	protected $debug = false;

	/**
	 * Ordered list of providers.
	 *
	 * @var array
	 */
	protected $providers = array();

	/**
	 * Automatically propogate values to previously specified providers
	 * which do not already have the value stored.
	 *
	 * @var bool
	 */
	protected $autoFillBack = true;
	/**
	 * Automatically propogate values to later specified providers
	 * which do not already have the value stored.
	 *
	 * Foward is more expensive than back since an exists check is performed
	 * before the write.
	 *
	 * @var bool
	 */
	protected $autoFillForward = true;
	/**
	 * Global blacklist for fill behavior. Serves as a small efficiency boost
	 * on top of the provider specific blacklists.
	 *
	 * @var array Array of regular expressions.
	 */
	protected $fillBlacklist = array();

	public function addProvider($provider) {
		$this->providers[] = $provider;
	}

	public function blacklistFill($regex) {
		$this->fillBlacklist[] = $regex;
	}

	protected function normalizeKey($key) {
		return trim($key, '/\\'. DIRECTORY_SEPARATOR . PATH_SEPARATOR);
	}

	public function autoFillBack($value = null) {
		if ($value === null) {
			return $this->autoFillBack;
		} else {
			$this->autoFillBack = (bool) $value;
		}
	}

	public function autoFillForward($value = null) {
		if ($value === null) {
			return $this->autoFillForward;
		} else {
			$this->autoFillForward = (bool) $value;
		}
	}

	protected function fillAllowed($key) {
		$blacklisted = false;
		foreach ($this->fillBlacklist as $blacklistRegex) {
			if (preg_match($blacklistRegex, $key)) {
				$blacklisted = true;
				break;
			}
		}
		return !$blacklisted;
	}

	protected function fill($providers, $key, $value, $checkExists = false) {
		if ($this->debug) {
			$providerClasses = array();
			foreach ($providers as $provider) {
				$providerClasses[] = get_class($provider);
			}
			\PEL::log('Storage attempting fill of key ('. $key .') to providers: '. implode(', ', $providerClasses), \PEL::LOG_DEBUG);
		}

		foreach ($providers as $provider) {
			if ($checkExists) {
				$exists = $provider->exists($key);
				if ($this->debug) {
					\PEL::log('Storage fill, '. get_class($provider) .'->exists('. $key .'): '. (($exists) ? 'true' : 'false'), \PEL::LOG_DEBUG);
				}
				if ($exists) {
					continue;
				}
			}

			if ($this->debug) {
				\PEL::log('Storage fill, '. get_class($provider) .'->put('. $key .', ...).', \PEL::LOG_DEBUG);
			}
			$provider->put($key, $value);
		}
	}

	public function exists($key) {
		$key = $this->normalizeKey($key);

		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			$result = $provider->exists($key);
			if ($this->debug) {
				\PEL::log('Storage exists, '. get_class($provider) .'->exists('. $key .'): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				return $result;
			}
		}
		return false;
	}

	/*
	 * All puts are currently slow but safe.
	 * If/when performance starts to become an issue switch to writing to
	 * first/last provider and then kickoff a cross-provider background sync.
	 * Gets auto-filling should be a last resort mostly reserved for recovery.
	*/
	public function put($key, $value, $expiration = null) {
		$key = $this->normalizeKey($key);

		$written = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			$result = $provider->put($key, $value, $expiration);
			if ($this->debug) {
				\PEL::log('Storage put, '. get_class($provider) .'->put('. $key .', ...): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				$written++;
			}
		}
		return ($written == count($this->providers)) ? true : false;
	}

	/**
	 * Compatibility with normal memcache interface.
	 */
	public function set($key, $value, $expiration = null) {
		$this->put($key, $value, $expiration);
	}

	public function putFile($key, $file, $expiration = null) {
		$key = $this->normalizeKey($key);

		$written = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			$result = $provider->putFile($key, $file, $expiration);
			if ($this->debug) {
				\PEL::log('Storage putFile, '. get_class($provider) .'->putFile('. $key .', ...): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				$written++;
			}
		}
		return ($written == count($this->providers)) ? true : false;
	}

	/* The get and getStream methods are kept separate rather than allowing one
	 * to use the other so that the underlying storage providers can optimize
	 * the two paths differently.
	 */

	protected function genericGet($getMethod, $key) {
		$key = $this->normalizeKey($key);

		$fillAllowed = $this->fillAllowed($key);
		$backfillProviders = array();

		foreach ($this->providers as $index => $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			$result = $provider->$getMethod($key);
			if ($this->debug) {
				\PEL::log('Storage '. $getMethod .', '. get_class($provider) .'->'. $getMethod .'('. $key .'): '. substr($result, 0, 10) .'...', \PEL::LOG_DEBUG);
			}
			if (!$result) {
				$backfillProviders[] = $provider;
			} else {
				if ($fillAllowed && ($this->autoFillBack || $this->autoFillForward)) {
					if ($this->autoFillBack && $backfillProviders) {
						$this->fill($backfillProviders, $key, $result);
					}

					if ($this->autoFillForward && ($index + 1) < count($this->providers)) {
						$fowardfillProviders = array_slice($this->providers, $index + 1);
						$this->fill($fowardfillProviders, $key, $result, true);
					}
				}

				return $result;
			}
		}

		return null;
	}

	public function get($key) {
		return $this->genericGet('get', $key);
	}

	public function getStream($key) {
		return $this->genericGet('getStream', $key);
	}

	public function getInfo($key) {
		$key = $this->normalizeKey($key);

		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			$result = $provider->getInfo($key);
			if ($this->debug) {
				\PEL::log('Storage getInfo, '. get_class($provider) .'->getInfo('. $key .'): '. print_r($result, true), \PEL::LOG_DEBUG);
			}
			if ($result) {
				return $result;
			}
		}
		return null;
	}

	public function delete($key) {
		$key = $this->normalizeKey($key);

		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key)) {
				continue;
			}

			if ($this->debug) {
				\PEL::log('Storage delete, '. get_class($provider) .'->delete('. $key .').', \PEL::LOG_DEBUG);
			}
			$provider->delete($key);
		}
	}
}

?>
