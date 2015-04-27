<?php

/**
 * Because those objects will be spawned during boostrap all its configuration
 * must be set in the settings.php file.
 *
 * For a detailed history of flush modes see:
 *   https://drupal.org/node/1980250
 *
 * You will find the driver specific implementation in the Redis_Cache_*
 * classes as they may differ in how the API handles transaction, pipelining
 * and return values.
 */
class Redis_Cache
    implements DrupalCacheInterface
{
    /**
     * Temporary cache items lifetime is infinite.
     */
    const LIFETIME_INFINITE = 0;

    /**
     * Default temporary cache items lifetime.
     */
    const LIFETIME_DEFAULT = 0;

    /**
     * Default lifetime for permanent items.
     * Approximatively 1 year.
     */
    const LIFETIME_PERM_DEFAULT = 31536000;

    /**
     * Flush nothing on generic clear().
     *
     * Because Redis handles keys TTL by itself we don't need to pragmatically
     * flush items by ourselves in most case: only 2 exceptions are the "page"
     * and "block" bins which are never expired manually outside of cron.
     */
    const FLUSH_NOTHING = 0;

    /**
     * Flush only temporary on generic clear().
     *
     * This dictate the cache backend to behave as the DatabaseCache default
     * implementation. This behavior is not documented anywere but hardcoded
     * there.
     */
    const FLUSH_TEMPORARY = 1;

    /**
     * This value is never used and only kept for configuration backward
     * compatibility. Please do never use this value for other configuration
     * constant.
     */
    const FLUSH_ALL = 2;

    /**
     * Never flush anything
     *
     * If you use your Redis server configured with LRU mecanism set on
     * volatile keys and if you have the default value or a maximum lifetime
     * set on you permanent entries, you can safely set your flush mode to
     * this one: it will never ever try to delete entries on flush but will
     * rely on runtime checks while reading the keys.
     */
    const FLUSH_NEVER = 3;

    /**
     * Computed keys are let's say arround 60 characters length due to
     * key prefixing, which makes 1,000 keys DEL command to be something
     * arround 50,000 bytes length: this is huge and may not pass into
     * Redis, let's split this off.
     * Some recommend to never get higher than 1,500 bytes within the same
     * command which makes us forced to split this at a very low threshold:
     * 20 seems a safe value here (1,280 average length).
     */
    const KEY_THRESHOLD = 20;

    /**
     * @var Redis_Cache_BackendInterface
     */
    protected $backend;

    /**
     * @var string
     */
    protected $bin;

    /**
     * @var int
     */
    protected $clearMode = self::FLUSH_TEMPORARY;

    /**
     * Default TTL for CACHE_PERMANENT items.
     *
     * See "Default lifetime for permanent items" section of README.txt
     * file for a comprehensive explaination of why this exists.
     *
     * @var int
     */
    protected $permTtl = self::LIFETIME_PERM_DEFAULT;

    /**
     * Maximum TTL for this bin from Drupal configuration.
     *
     * @var int
     */
    protected $maxTtl = 0;

    /**
     * Get clear mode.
     *
     * @return int
     *   One of the Redis_Cache_Base::FLUSH_* constant.
     */
    public function getClearMode()
    {
        return $this->clearMode;
    }

    /**
     * Get TTL for CACHE_PERMANENT items.
     *
     * @return int
     *   Lifetime in seconds.
     */
    public function getPermTtl()
    {
        return $this->permTtl;
    }

    /**
     * Get maximum TTL for all items.
     *
     * @return int
     *   Lifetime in seconds.
     */
    public function getMaxTtl()
    {
        return $this->maxTtl;
    }

    public function __construct($bin)
    {
        $this->bin = $bin;

        $className = Redis_Client::getClass(Redis_Client::REDIS_IMPL_CACHE);
        $this->backend = new $className(Redis_Client::getClient(), $bin, Redis_Client::getDefaultPrefix($bin));

        $this->refreshClearMode();
        $this->refreshPermTtl();
        $this->refreshMaxTtl();
    }

    /**
     * Find from Drupal variables the clear mode.
     */
    public function refreshClearMode()
    {
        if (0 < variable_get('cache_lifetime', 0)) {
            // Per Drupal default behavior, when the 'cache_lifetime' variable
            // is set we must not flush any temporary items since they have a
            // life time.
            $this->clearMode = self::FLUSH_NOTHING;
        } else if (null !== ($mode = variable_get('redis_flush_mode_' . $this->bin, null))) {
            // A bin specific flush mode has been set.
            $this->clearMode = (int)$mode;
        } else if (null !== ($mode = variable_get('redis_flush_mode', null))) {
            // A site wide generic flush mode has been set.
            $this->clearMode = (int)$mode;
        } else {
            // No flush mode is set by configuration: provide sensible defaults.
            // See FLUSH_* constants for comprehensible explaination of why this
            // exists.
            switch ($this->bin) {

                case 'cache_page':
                case 'cache_block':
                    $this->clearMode = self::FLUSH_TEMPORARY;
                    break;

                default:
                    $this->clearMode = self::FLUSH_NOTHING;
                    break;
            }
        }
    }

    /**
     * Find from Drupal variables the right permanent items TTL.
     */
    protected function refreshPermTtl()
    {
        $ttl = null;
        if (null === ($ttl = variable_get('redis_perm_ttl_' . $this->bin, null))) {
            if (null === ($ttl = variable_get('redis_perm_ttl', null))) {
                $ttl = self::LIFETIME_PERM_DEFAULT;
            }
        }
        if ($ttl === (int)$ttl) {
            $this->permTtl = $ttl;
        } else {
            if ($iv = DateInterval::createFromDateString($ttl)) {
                // http://stackoverflow.com/questions/14277611/convert-dateinterval-object-to-seconds-in-php
                $this->permTtl = ($iv->y * 31536000 + $iv->m * 2592000 + $iv->d * 86400 + $iv->h * 3600 + $iv->i * 60 + $iv->s);
            } else {
                // Sorry but we have to log this somehow.
                trigger_error(sprintf("Parsed TTL '%s' has an invalid value: switching to default", $ttl));
                $this->permTtl = self::LIFETIME_PERM_DEFAULT;
            }
        }
    }

    /**
     * Find from Drupal variables the maximum cache lifetime.
     */
    public function refreshMaxTtl()
    {
        // And now cache lifetime. Be aware we exclude negative values
        // considering those are Drupal misconfiguration.
        $maxTtl = variable_get('cache_lifetime', 0);
        if (0 < $maxTtl) {
            if ($maxTtl < $this->permTtl) {
                $this->maxTtl = $maxTtl;
            } else {
                $this->maxTtl = $this->permTtl;
            }
        } else if ($this->permTtl) {
            $this->maxTtl = $this->permTtl;
        }
    }

    /**
     * Create cache entry.
     *
     * @param string $cid
     * @param mixed $data
     *
     * @return array
     */
    protected function createEntryHash($cid, $data, $expire = CACHE_PERMANENT)
    {
        list($flushPerm, $flushVolatile) = $this->backend->getLastFlushTime();

        if (CACHE_TEMPORARY === $expire) {
            $validityThreshold = max(array($flushVolatile, $flushPerm));
        } else {
            $validityThreshold = $flushPerm;
        }

        $time = time();
        if ($time === (int)$validityThreshold) {
            // Latest flush happened the exact same second.
            $time = $validityThreshold;
        } else {
            $time = $this->getNextIncrement($time);
        }

        $hash = array(
            'cid'     => $cid,
            'created' => $time,
            'expire'  => $expire,
        );

        // Let Redis handle the data types itself.
        if (!is_string($data)) {
            $hash['data'] = serialize($data);
            $hash['serialized'] = 1;
        } else {
            $hash['data'] = $data;
            $hash['serialized'] = 0;
        }

        return $hash;
    }

    /**
     * Expand cache entry from fetched data.
     *
     * @param array $values
     *
     * @return array
     *   Or FALSE if entry is invalid
     */
    protected function expandEntry(array $values, $flushPerm, $flushVolatile)
    {
        // Check for entry being valid.
        if (empty($values['cid'])) {
            return;
        }

        // This ensures backward compatibility with older version of
        // this module's data still stored in Redis.
        if (isset($values['expire'])) {
            // Ensure the entry is valid and have not expired.
            $expire = (int)$values['expire'];

            if ($expire !== CACHE_PERMANENT && $expire !== CACHE_TEMPORARY && $expire <= time()) {
                return false;
            }
        }

        // Ensure the entry does not predate the last flush time.
        if ($values['volatile']) {
            $validityThreshold = $flushVolatile;
        } else {
            $validityThreshold = $flushPerm;
        }

        if ($values['created'] < $validityThreshold) {
            return false;
        }

        $entry = (object)$values;

        if ($entry->serialized) {
            $entry->data = unserialize($entry->data);
        }

        return $entry;
    }

    public function get($cid)
    {
        $values = $this->backend->get($cid);

        if (empty($values)) {
            return false;
        }

        list($flushPerm, $flushVolatile) = $this->backend->getLastFlushTime();

        $entry = $this->expandEntry($values, $flushPerm, $flushVolatile);

        if (!$entry) { // This entry exists but is invalid.
            $this->backend->delete($cid);
            return false;
        }

        return $entry;
    }

    public function getMultiple(&$cids)
    {
        $map    = drupal_map_assoc($cids);
        $ret    = array();
        $delete = array();

        $entries = $this->backend->getMultiple($map);

        list($flushPerm, $flushVolatile) = $this->backend->getLastFlushTime();

        $map = array_flip($map);
        if (!empty($entries)) {
            foreach ($entries as $id => $values) {

                $entry = $this->expandEntry($values, $flushPerm, $flushVolatile);

                if (empty($entry)) {
                    $delete[] = $id;
                    unset($map[$id]);
                } else {
                    $cid = $map[$id];
                    $ret[$cid] = $entry;
                }
            }
        }

        if (!empty($delete)) {
            $this->backend->deleteMultiple($delete);
        }

        // @todo Note sure this will update the referenced array thought.
        $cids = array_diff($cids, $map);

        return $ret;
    }

    public function set($cid, $data, $expire = CACHE_PERMANENT)
    {
        $hash   = $this->createEntryHash($cid, $data, $expire);
        $maxTtl = $this->getMaxTtl();

        switch ($expire) {

            case CACHE_PERMANENT:
                $this->backend->set($cid, $hash, $maxTtl, false);
                break;

            case CACHE_TEMPORARY:
                $this->backend->set($cid, $hash, $maxTtl, true);
                break;

            default:
                $ttl = $expire - time();
                // Ensure $expire consistency
                if ($ttl <= 0) {
                    // Entry has already expired, but we may have a stalled
                    // older cache entry remaining there, ensure it wont
                    // happen by doing a preventive delete
                    $this->backend->delete($cid);
                } else {
                    if ($maxTtl && $maxTtl < $ttl) {
                        $ttl = $maxTtl;
                    }
                    $this->backend->set($cid, $hash, $ttl, false);
                }
                break;
        }
    }

    public function clear($cid = null, $wildcard = false)
    {
        $clearMode = $this->getClearMode();

        // This is only for readability
        $backend = $this->backend;

        list($flushPerm, $flushVolatile) = $this->backend->getLastFlushTime();

        if (null === $cid && !$wildcard) {
            switch ($clearMode) {

                // One and only case of early return, fastest implementation
                // business valid for anything else than 'page' and 'block'.
                case self::FLUSH_NEVER:
                case self::FLUSH_NOTHING:
                    $backend->setLastFlushTimeFor($this->getNextIncrement($flushVolatile), true);
                    break;

                // Drupal default behavior but slowest implementation for
                // most backends because this needs a full scan.
                default:
                case self::FLUSH_TEMPORARY:
                    $this->backend->flushVolatile();
                    break;
            }
        } else if ($wildcard) {

            if (empty($cid)) {
                // This seems to be an error, just do nothing.

            } else if ('*' === $cid) {

                // Use max() to ensure we invalidate both correctly.
                $this->backend->setLastFlushTimeFor(max(array($flushPerm, $flushVolatile)));

                if (self::FLUSH_NEVER !== $clearMode) {
                    $this->backend->flush();
                }
            } else {

                // @todo This needs a map algorithm the same way memcache module
                // implemented it for invalidity by prefixes.
                if (self::FLUSH_NEVER !== $clearMode) {
                    $this->backend->deleteByPrefix($cid);
                } else {
                    // @todo Very stupid working fallback.
                    $this->backend->setLastFlushTimeFor(max(array($flushPerm, $flushVolatile)));
                }
            }
        } else if (is_array($cid)) {
            $this->backend->deleteMultiple($cid);
        } else {
            $this->backend->delete($cid);
        }
    }

    public function isEmpty()
    {
       return false;
    }

    /**
     * From the given timestamp build an incremental safe time-based identifier.
     *
     * Due to potential accidental cache wipes, when a server goes down in the
     * cluster or when a server triggers its LRU algorithm wipe-out, keys that
     * matches flush or tags checksum might be dropped.
     *
     * Per default, each new inserted tag will trigger a checksum computation to
     * be stored in the Redis server as a timestamp. In order to ensure a checksum
     * validity a simple comparison between the tag checksum and the cache entry
     * checksum will tell us if the entry pre-dates the current checksum or not,
     * thus telling us its state. The main problem we experience is that Redis
     * is being so fast it is able to create and drop entries at same second,
     * sometime even the same micro second. The only safe way to avoid conflicts
     * is to checksum using an arbitrary computed number (a sequence).
     *
     * Drupal core does exactly this thus tags checksums are additions of each tag
     * individual checksum; each tag checksum is a independent arbitrary serial
     * that gets incremented starting with 0 (no invalidation done yet) to n (n
     * invalidations) which grows over time. This way the checksum computation
     * always rises and we have a sensible default that works in all cases.
     *
     * This model works as long as you can ensure consistency for the serial
     * storage over time. Nevertheless, as explained upper, in our case this
     * serial might be dropped at some point for various valid technical reasons:
     * if we start over to 0, we may accidentally compute a checksum which already
     * existed in the past and make invalid entries turn back to valid again.
     *
     * In order to prevent this behavior, using a timestamp as part of the serial
     * ensures that we won't experience this problem in a time range wider than a
     * single second, which is safe enough for us. But using timestamp creates a
     * new problem: Redis is so fast that we can set or delete hundreds of entries
     * easily during the same second: an entry created then invalidated the same
     * second will create false positives (entry is being considered as valid) -
     * note that depending on the check algorithm, false negative may also happen
     * the same way. Therefore we need to have an abitrary serial value to be
     * incremented in order to enforce our checks to be more strict.
     *
     * The solution to both the first (the need for a time based checksum in case
     * of checksum data being dropped) and the second (the need to have an
     * arbitrary predictible serial value to avoid false positives or negatives)
     * we are combining the two: every checksum will be built this way:
     *
     *   UNIXTIMESTAMP.SERIAL
     *
     * For example:
     *
     *   1429789217.017
     *
     * will reprensent the 17th invalidation of the 1429789217 exact second which
     * happened while writing this documentation. The next tag being invalidated
     * the same second will then have this checksum:
     *
     *   1429789217.018
     *
     * And so on...
     *
     * In order to make it consitent with PHP string and float comparison we need
     * to set fixed precision over the decimal, and store as a string to avoid
     * possible float precision problems when comparing.
     *
     * This algorithm is not fully failsafe, but allows us to proceed to 1000
     * operations on the same checksum during the same second, which is a
     * sufficiently great value to reduce the conflict probability to almost
     * zero for most uses cases.
     *
     * @param int|string $timestamp
     *   "TIMESTAMP[.INCREMENT]" string
     *
     * @return string
     *   The next "TIMESTAMP.INCREMENT" string.
     */
    public function getNextIncrement($timestamp = null)
    {
        if (!$timestamp) {
            return time() . '.000';
        }

        if (false !== ($pos = strpos($timestamp, '.'))) {
            $inc = substr($timestamp, $pos + 1, 3);

            return ((int)$timestamp) . '.' . str_pad($inc + 1, 3, '0', STR_PAD_LEFT);
        }

        return $timestamp . '.000';
    }
}
