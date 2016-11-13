<?php

/**
 * @file Redis_Cache_Compressed.php
 *
 * This typically brings 80..85% compression in ~20ms/mb write, 5ms/mb read.
 */
class Redis_CacheCompressed extends Redis_Cache implements DrupalCacheInterface {

  protected function createEntryHash($cid, $data, $expire = CACHE_PERMANENT) {
    $hash = parent::createEntryHash($cid, $data, $expire);
    // Empiric level when compression makes sense.
    if (strlen($hash['data']) > 100) {
      // Minimum compression level has good ratio in low time.
      $hash['data'] = gzcompress($hash['data'], 1);
      $hash['compressed'] = TRUE;
    }
    return $hash;
  }

  protected function expandEntry(array $values, $flushPerm, $flushVolatile) {
    if (!empty($values['data']) && !empty($values['compressed'])) {
      // Uncompress, suppress warnings e.g. for broken CRC32.
      $values['data'] = @gzuncompress($values['data']);
      // In such cases, void the cache entry.
      if ($values['data'] === FALSE) {
        return FALSE;
      }
    }
    return parent::expandEntry($values, $flushPerm, $flushVolatile);
  }

}
