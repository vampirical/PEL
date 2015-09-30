<?php

namespace PEL;

/**
 * Storage
 *
 * Abstract key value storage layer, designed with file storage in mind but
 * flexible by way of different "provider" configurations with optional "fill"
 * behaviors. A provider is a driver for a specific storage medium such as a
 * local filesystem, a remote storage system such as S3, a Memcache cluster,
 * etc. Fills are duplication of values between providers which can occur in
 * two directions, either forwards to a later provider in the stack or back
 * to an earlier provider. By configuring an ordered set of providers and fill
 * settings, both top level and per provider, you can achieve different
 * optimized storage scenarios behind a very simple interface.
 *
 * Configuration:
 *   addProvider($provider)
 *   blacklistFill($regex)
 *   autoFillBack($value = null)
 *   autoFillForward($value = null)
 *
 * Item management:
 *   exists($key)
 *   set($key, $value, $expiration = null)
 *   setFile($key, $file, $expiration = null)
 *   get($key)
 *   getStream($key)
 *   getInfo($key)
 *   delete($key)
 */
class Storage
{
	const ACCESS_READ = 'read';
	const ACCESS_WRITE = 'write';

	/**
	 * Enable debug behavior and logging.
	 *
	 * @var bool
	 */
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
	 * Automatically propagate values to later specified providers
	 * which do not already have the value stored.
	 *
	 * Forward is more expensive than back since an exists check is performed
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

	/**
	 * Temp files
	 *
	 * @var array
	 */
	protected $tempFiles = array();

	/**
	 * Destruct
	 *
	 * Removes temp files created by
	 *
	 * @return void
	 */
	public function __destruct() {
		foreach ($this->tempFiles as $tempFile) {
			@unlink($tempFile);
		}
	}

	/**
	 * Normalize Key
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function normalizeKey($key) {
		return trim($key, '/\\'. DIRECTORY_SEPARATOR . PATH_SEPARATOR);
	}

	/**
	 * Fill Allowed
	 *
	 * Checks top level fill blacklist for a key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
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

	/**
	 * Fill
	 *
	 * @param array  $providers
	 * @param string $key
	 * @param mixed  $value
	 * @param bool   $checkExists
	 *
	 * @return void
	 */
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
				// Skip exists check if write isn't allowed.
				if (!$provider->allowed($key, self::ACCESS_WRITE)) {
					continue;
				}

				$exists = $provider->exists($key);
				if ($this->debug) {
					\PEL::log('Storage fill, '. get_class($provider) .'->exists('. $key .'): '. (($exists) ? 'true' : 'false'), \PEL::LOG_DEBUG);
				}
				if ($exists) {
					continue;
				}
			}

			if (is_resource($value)) {
				if ($this->debug) {
					\PEL::log('Storage fill, '. get_class($provider) .'->setStream('. $key .', ...).', \PEL::LOG_DEBUG);
				}
				$provider->setStream($key, $value);
			} else {
				if ($this->debug) {
					\PEL::log('Storage fill, '. get_class($provider) .'->set('. $key .', ...).', \PEL::LOG_DEBUG);
				}
				$provider->set($key, $value);
			}
		}
	}

	/**
	 * Generic get, common logic for get and getStream methods.
	 *
	 * The get and getStream methods are kept separate rather than allowing one
	 * to use the other so that the underlying storage providers can optimize
	 * the two paths differently. This internal method is used by get and
	 * getStream to consolidate the, at this level, completely shared logic.
	 *
	 * @param string   $getMethod
	 * @param string   $key
	 * @param resource $stream
	 *
	 * @return mixed
	 */
	protected function genericGet($getMethod, $key, $stream = null) {
		$key = $this->normalizeKey($key);

		$fillAllowed = $this->fillAllowed($key);
		$backfillProviders = array();

		foreach ($this->providers as $index => $provider) {
			if (!$provider->allowed($key, self::ACCESS_READ)) {
				continue;
			}

			$result = $provider->$getMethod($key, $stream);
			if ($this->debug) {
				$displayValue = $result;
				if (is_string($displayValue)) {
					$displayValue = substr($displayValue, 0, 10);
				}
				if (is_bool($displayValue)) {
					$displayValue = ($displayValue) ? 'true' : 'false';
				}
				\PEL::log('Storage '. $getMethod .', '. get_class($provider) .'->'. $getMethod .'('. $key .'): '. $displayValue .'...', \PEL::LOG_DEBUG);
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

	/***** Public Methods *****/

	/**
	 * Add Provider
	 *
	 * @param Provider $provider
	 *
	 * @return void
	 */
	public function addProvider($provider) {
		$this->providers[] = $provider;
	}

	/**
	 * Blacklist Fill
	 *
	 * @param string $regex
	 *
	 * @return void
	 */
	public function blacklistFill($regex) {
		$this->fillBlacklist[] = $regex;
	}

	/**
	 * Auto Fill Back
	 *
	 * get/set autoFillBack value
	 *
	 * @param bool|null $value Sets value if provided.
	 *
	 * @return bool|null If no value is provided, return current value.
	 */
	public function autoFillBack($value = null) {
		if ($value === null) {
			return $this->autoFillBack;
		} else {
			$this->autoFillBack = (bool) $value;
		}
	}

	/**
	 * Auto Fill Forward
	 *
	 * get/set autoFillForward value
	 *
	 * @param bool|null $value Sets value if provided.
	 *
	 * @return bool|null If no value is provided, return current value.
	 */
	public function autoFillForward($value = null) {
		if ($value === null) {
			return $this->autoFillForward;
		} else {
			$this->autoFillForward = (bool) $value;
		}
	}

	/**
	 * Exists
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function exists($key) {
		$key = $this->normalizeKey($key);

		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_READ)) {
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

	/**
	 * Set
	 *
	 * All sets are currently slow but safe.
	 * If/when performance starts to become an issue switch to writing to
	 * first/last provider and then kickoff a cross-provider background sync.
	 * Gets auto-filling should be a last resort mostly reserved for recovery.
	 *
	 * @param string  $key
	 * @param mixed   $value
	 * @param int     $expiration
	 *
	 * @return bool
	 */
	public function set($key, $value, $expiration = null) {
		$key = $this->normalizeKey($key);

		$allowed = 0;
		$written = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_WRITE)) {
				continue;
			}
			++$allowed;

			$result = $provider->set($key, $value, $expiration);
			if ($this->debug) {
				\PEL::log('Storage set, '. get_class($provider) .'->set('. $key .', ...): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				++$written;
			}
		}

		return ($allowed > 0 && ($written === $allowed));
	}

	/**
	 * Set Stream
	 *
	 * All sets are currently slow but safe.
	 * If/when performance starts to become an issue switch to writing to
	 * first/last provider and then kickoff a cross-provider background sync.
	 * Gets auto-filling should be a last resort mostly reserved for recovery.
	 *
	 * @param string   $key
	 * @param resource $stream
	 * @param int      $expiration
	 *
	 * @return bool
	 */
	public function setStream($key, $stream, $expiration = null) {
		$key = $this->normalizeKey($key);

		$allowed = 0;
		$written = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_WRITE)) {
				continue;
			}
			++$allowed;

			$result = $provider->setStream($key, $stream, $expiration);
			if ($this->debug) {
				\PEL::log('Storage setStream, '. get_class($provider) .'->setStream('. $key .', ...): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				++$written;
			}
		}

		return ($allowed > 0 && ($written === $allowed));
	}

	/**
	 * Set File
	 *
	 * All sets are currently slow but safe.
	 * If/when performance starts to become an issue switch to writing to
	 * first/last provider and then kickoff a cross-provider background sync.
	 * Gets auto-filling should be a last resort mostly reserved for recovery.
	 *
	 * @param string $key
	 * @param mixed  $file       Path to file.
	 * @param int    $expiration
	 *
	 * @return bool
	 */
	public function setFile($key, $file, $expiration = null) {
		$key = $this->normalizeKey($key);

		$allowed = 0;
		$written = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_WRITE)) {
				continue;
			}
			++$allowed;

			$result = $provider->setFile($key, $file, $expiration);
			if ($this->debug) {
				\PEL::log('Storage setFile, '. get_class($provider) .'->setFile('. $key .', ...): '. $result, \PEL::LOG_DEBUG);
			}
			if ($result) {
				++$written;
			}
		}

		return ($allowed > 0 && ($written === $allowed));
	}

	/**
	 * Get
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get($key) {
		return $this->genericGet('get', $key);
	}

	/**
	 * Get Stream
	 *
	 * @param string   $key
	 * @param resource $stream Optional stream to write to
	 *
	 * @return resource|null
	 */
	public function getStream($key, $stream = null) {
		return $this->genericGet('getStream', $key, $stream);
	}

	/**
	 * Get Info
	 *
	 * @param string $key
	 *
	 * @return array|null Array of meta data properties or null if no providers.
	 */
	public function getInfo($key) {
		$key = $this->normalizeKey($key);

		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_READ)) {
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

	/**
	 * Get as Temp File
	 *
	 * Creates a temporary file with the content of a key. File will be
	 * automatically deleted when Storage instance destructs.
	 *
	 * @param string $key
	 * @param string $tempFilePath Full path to temporary file.
	 *
	 * @return string Full path to temporary file.
	 */
	public function getAsTempFile($key, $tempFilePath = null) {
		if (!$tempFilePath) {
			$tempFilePath = tempnam(sys_get_temp_dir(), 'pel-storage-');
		}

		$this->tempFiles[] = $tempFilePath;

		file_put_contents($tempFilePath, $this->get($key));

		return $tempFilePath;
	}

	/**
	 * Delete
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete($key) {
		$key = $this->normalizeKey($key);

		$allowed = 0;
		$deleted = 0;
		foreach ($this->providers as $provider) {
			if (!$provider->allowed($key, self::ACCESS_WRITE)) {
				continue;
			}
			++$allowed;

			if ($this->debug) {
				\PEL::log('Storage delete, '. get_class($provider) .'->delete('. $key .').', \PEL::LOG_DEBUG);
			}
			$result = $provider->delete($key);
			if ($result) {
				++$deleted;
			}
		}

		return ($allowed > 0 && ($deleted === $allowed));
	}
}

?>
