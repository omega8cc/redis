<?php

/**
 * PhpRedis implementation.
 *
 * @todo
 *   Set high expire value to the hash for rotation when memory is empty
 *   React upon cache clear all and rebuild path list?
 */
class Redis_Path_PhpRedis extends Redis_Path_AbstractHashLookup
{
    protected function saveInHash($key, $hkey, $hvalue)
    {
        $client = Redis_Client::getClient();

        $value = $client->hget($key, $hkey);

        if ($value) {
            $existing = explode(self::VALUE_SEPARATOR, $value);
            if (!in_array($hvalue, $existing)) {
                $value .= self::VALUE_SEPARATOR . $hvalue;
            } else { // Do nothing on empty value
              $value = null;
            }
        } else if (empty($value)) {
            $value = self::VALUE_NULL;
        } else {
            $value = $hvalue;
        }

        if (!empty($value)) {
            $client->hset($key, $hkey, $value);
        }
        // Empty value here means that we already got it
    }

    public function saveAlias($source, $alias, $language = null)
    {
        if (null === $language) {
            $language = LANGUAGE_NONE;
        }

        if (!empty($source)) {
            $this->saveInHash($this->getKey(self::KEY_ALIAS, $language), $source, $alias);
        }
        if (!empty($alias)) {
            $this->saveInHash($this->getKey(self::KEY_SOURCE, $language), $alias, $source);
        }
    }

    protected function deleteInHash($key, $hkey, $hvalue)
    {
        $client = Redis_Client::getClient();

        $value = $client->hget($key, $hkey);

        if ($value) {
            $existing = explode(self::VALUE_SEPARATOR, $value);
            if ($index = array_search($hvalue, $existing)) {
                if (1 === count($existing)) {
                    $client->hdel($key, $hkey);
                } else {
                    unset($existing[$index]);
                    $client->hset($key, $hkey, implode(self::VALUE_SEPARATOR, $existing));
                }
            }
        }
    }

    public function deleteAlias($source, $alias, $language = null)
    {
        if (null === $language) {
            $language = LANGUAGE_NONE;
        }

        $this->deleteInHash($this->getKey(self::KEY_ALIAS, $language), $source, $alias);
        $this->deleteInHash($this->getKey(self::KEY_SOURCE, $language), $alias, $source);
    }

    public function deleteLanguage($language)
    {
        $client = Redis_Client::getClient();
        $client->del($this->getKey(self::KEY_ALIAS, $language));
        $client->del($this->getKey(self::KEY_SOURCE, $language));
    }

    public function lookupInHash($keyPrefix, $hkey, $language = null)
    {
        $client = Redis_Client::getClient();

        if (null === $language) {
            $language = LANGUAGE_NONE;
            $doNoneLookup = false;
        } else {
            $doNoneLookup = true;
        }

        $ret = $client->hget($this->getKey($keyPrefix, $language), $hkey);
        if (!$ret && $doNoneLookup) {
            $ret = $client->hget($this->getKey($keyPrefix, LANGUAGE_NONE), $hkey);
        }

        if ($ret === self::VALUE_NULL) {
            return false; // Needs conversion
        }
        if (empty($ret)) {
            return null; // Value not found
        }

        $existing = explode(self::VALUE_SEPARATOR, $ret);

        return reset($existing);
    }

    public function lookupAlias($source, $language = null)
    {
        return $this->lookupInHash(self::KEY_ALIAS, $source, $language);
    }

    public function lookupSource($alias, $language = null)
    {
        return $this->lookupInHash(self::KEY_SOURCE, $alias, $language);
    }
}
