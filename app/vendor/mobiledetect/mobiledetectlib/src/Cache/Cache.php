<?php

declare(strict_types=1);

namespace Detection\Cache;

use Psr\SimpleCache\CacheInterface;
use DateInterval;
use DateTime;

use function is_int;
use function is_iterable;
use function is_string;
use function time;

/**
 * In-memory cache implementation of PSR-16.
 *
 * @see https://www.php-fig.org/psr/psr-16/
 *
 * Public method parameters are intentionally left without scalar type
 * declarations (return types are kept). This keeps the class
 * Liskov-compatible with PSR-16 v1/v2 as well as v3, so it loads cleanly
 * in hosts (e.g. WordPress sites) where another plugin's autoloader has
 * already registered an older `Psr\SimpleCache\CacheInterface`.
 * Re-narrowing these parameters will break that compatibility. See
 * https://github.com/serbanghita/Mobile-Detect/issues/989.
 */
class Cache implements CacheInterface
{
    protected array $cache = [];

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     * @throws CacheInvalidArgumentException
     */
    public function get($key, mixed $default = null): mixed
    {
        $key = $this->checkKey($key);

        if (isset($this->cache[$key])) {
            if ($this->cache[$key]['ttl'] === null || $this->cache[$key]['ttl'] > time()) {
                return $this->cache[$key]['content'];
            }

            $this->deleteSingle($key);
        }

        return $default;
    }

    /**
     * @param string                $key
     * @param mixed                 $value
     * @param int|DateInterval|null $ttl
     * @throws CacheInvalidArgumentException
     */
    public function set($key, mixed $value, $ttl = null): bool
    {
        $key = $this->checkKey($key);
        $ttl = $this->checkTtl($ttl);

        // From https://www.php-fig.org/psr/psr-16/ "Definitions" -> "Expiration"
        // If a negative or zero TTL is provided, the item MUST be deleted from the cache if it exists, as it is expired already.
        if (is_int($ttl) && $ttl <= 0) {
            $this->deleteSingle($key);
            return false;
        }

        $ttl = $this->getTTL($ttl);

        if ($ttl !== null) {
            $ttl = (time() + $ttl);
        }

        $this->cache[$key] = ['ttl' => $ttl, 'content' => $value];

        return true;
    }

    /**
     * @param string $key
     * @throws CacheInvalidArgumentException
     */
    public function delete($key): bool
    {
        $key = $this->checkKey($key);
        $this->deleteSingle($key);

        return true;
    }

    /**
     * Deletes the cache item from memory.
     */
    private function deleteSingle(string $key): void
    {
        unset($this->cache[$key]);
    }

    /** @inheritdoc */
    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    /**
     * @param string $key
     * @throws CacheInvalidArgumentException
     */
    public function has($key): bool
    {
        $key = $this->checkKey($key);

        if (isset($this->cache[$key])) {
            if ($this->cache[$key]['ttl'] === null || $this->cache[$key]['ttl'] > time()) {
                return true;
            }

            $this->deleteSingle($key);
        }

        return false;
    }

    /**
     * @param iterable<string> $keys
     * @param mixed            $default
     * @throws CacheInvalidArgumentException
     */
    public function getMultiple($keys, mixed $default = null): iterable
    {
        $keys = $this->checkIterable($keys, 'keys');

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }

        return $data;
    }

    /**
     * @param iterable<string, mixed> $values
     * @param int|DateInterval|null   $ttl
     * @throws CacheInvalidArgumentException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $values = $this->checkIterable($values, 'values');
        $ttl = $this->checkTtl($ttl);

        $return = [];
        foreach ($values as $key => $value) {
            $return[] = $this->set($key, $value, $ttl);
        }

        return $this->checkReturn($return);
    }

    /**
     * @param iterable<string> $keys
     * @throws CacheInvalidArgumentException
     */
    public function deleteMultiple($keys): bool
    {
        $keys = $this->checkIterable($keys, 'keys');

        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param mixed $key
     * @throws CacheInvalidArgumentException
     */
    protected function checkKey($key): string
    {
        if (!is_string($key)) {
            throw new CacheInvalidArgumentException('Cache key must be a string.');
        }

        if ($key === '' || !preg_match('/^[A-Za-z0-9_.]{1,64}$/', $key)) {
            throw new CacheInvalidArgumentException("Invalid key: '$key'. Must be alphanumeric, can contain _ and . and can be maximum of 64 chars.");
        }

        return $key;
    }

    /**
     * @param mixed $ttl
     * @throws CacheInvalidArgumentException
     */
    protected function checkTtl($ttl): int|DateInterval|null
    {
        if ($ttl !== null && !is_int($ttl) && !($ttl instanceof DateInterval)) {
            throw new CacheInvalidArgumentException('TTL must be null, int, or DateInterval.');
        }

        return $ttl;
    }

    /**
     * @param mixed $iterable
     * @return iterable<mixed>
     * @throws CacheInvalidArgumentException
     */
    protected function checkIterable($iterable, string $argName): iterable
    {
        if (!is_iterable($iterable)) {
            throw new CacheInvalidArgumentException(sprintf('%s must be iterable.', ucfirst($argName)));
        }

        return $iterable;
    }

    protected function getTTL(DateInterval|int|null $ttl): ?int
    {
        if ($ttl instanceof DateInterval) {
            return (new DateTime())->add($ttl)->getTimestamp() - time();
        }

        // We treat 0 as a valid value.
        if (is_int($ttl)) {
            return $ttl;
        }

        return null;
    }

    /**
     * @param bool[]|int[] $booleans
     */
    protected function checkReturn(array $booleans): bool
    {
        foreach ($booleans as $boolean) {
            if (!$boolean) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all cache keys.
     *
     * @internal Needed for testing purposes.
     * @return array{string}
     */
    public function getKeys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * Evict all expired items from the cache.
     *
     * Useful for long-running processes (CLI scripts, workers, daemons)
     * to periodically clean up expired entries and free memory.
     *
     * @return int Number of items evicted
     */
    public function evictExpired(): int
    {
        $evicted = 0;
        $now = time();

        foreach ($this->cache as $key => $item) {
            if ($item['ttl'] !== null && $item['ttl'] <= $now) {
                unset($this->cache[$key]);
                $evicted++;
            }
        }

        return $evicted;
    }
}
