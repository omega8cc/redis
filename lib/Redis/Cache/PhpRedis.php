<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_PhpRedis extends Redis_Cache_Base
{
    public function setLastFlushTimeFor($time, $volatile = false)
    {
        $client = Redis_Client::getClient();
        $key    = $this->getNamespace() . '-' . self::LAST_FLUSH_KEY;

        if ($volatile) {
            $client->hset($key, 'volatile', $time);
        } else {
            $client->hmset($key, array(
                'permanent' => $time,
                'volatile' => $time,
            ));
        }
    }

    public function getLastFlushTime()
    {
        $client = Redis_Client::getClient();
        $key    = $this->getNamespace() . '-' . self::LAST_FLUSH_KEY;
        $values = $client->hmget($key, array("permanent", "volatile"));

        if (empty($values) || !is_array($values)) {
            $ret = array(0, 0);
        } else {
            if (empty($values['permanent'])) {
                $values['permanent'] = 0;
            }
            if (empty($values['volatile'])) {
                $values['volatile'] = 0;
            }
            $ret = array($values['permanent'], $values['volatile']);
        }

        return $ret;
    }

    public function get($id)
    {
        $client = Redis_Client::getClient();
        $values = $client->hgetall($id);

        // Recent versions of PhpRedis will return the Redis instance
        // instead of an empty array when the HGETALL target key does
        // not exists. I see what you did there.
        if (empty($values) || !is_array($values)) {
            return false;
        }

        return $values;
    }

    public function getMultiple(array $idList)
    {
        $client = Redis_Client::getClient();

        $ret = array();

        $pipe = $client->multi(Redis::PIPELINE);
        foreach ($idList as $id) {
            $pipe->hgetall($id);
        }
        $replies = $pipe->exec();

        foreach (array_values($idList) as $line => $id) {
            if (!empty($replies[$line]) && is_array($replies[$line])) {
                $ret[$id] = $replies[$line];
            }
        }

        return $ret;
    }

    public function set($id, $data, $ttl = null, $volatile = false)
    {
        // Ensure TTL consistency: if the caller gives us an expiry timestamp
        // in the past the key will expire now and will never be read.
        // Behavior between Predis and PhpRedis seems to change here: when
        // setting a negative expire time, PhpRedis seems to ignore the
        // command and leave the key permanent.
        if (null !== $ttl && $ttl <= 0) {
            return;
        }

        $data['volatile'] = (int)$volatile;

        $client = Redis_Client::getClient();

        $pipe = $client->multi(Redis::PIPELINE);
        $pipe->hmset($id, $data);

        if (null !== $ttl) {
            $pipe->expire($id, $ttl);
        }
        $pipe->exec();
    }

    public function delete($id)
    {
        $client = Redis_Client::getClient();
        $client->del($id);
    }

    public function deleteMultiple(array $idList)
    {
        $client = Redis_Client::getClient();
        $client->del($idList);
    }

    public function deleteByPrefix($prefix)
    {
        $client = Redis_Client::getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, array($prefix . '*'));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }

    public function flush()
    {
        $client = Redis_Client::getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, array($this->getNamespace() . '*'));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }

    public function flushVolatile()
    {
        $client = Redis_Client::getClient();
        $ret = $client->eval(self::EVAL_DELETE_VOLATILE, array($this->getNamespace() . '*'));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }
}
