<?php

namespace Drupal\redis;

use Drupal\Core\Site\Settings;

trait RedisPrefixTrait {

  /**
   * @var string
   */
  protected $prefix;

  /**
   * Get global default prefix
   *
   * @param string $suffix
   *
   * @return string
   */
  protected function getDefaultPrefix($suffix = NULL) {
    $ret = NULL;

    if ($test_prefix = drupal_valid_test_ua()) {
      $ret = $test_prefix;
    }
    else {
      $prefixes = Settings::get('cache_prefix', '');

      if (is_string($prefixes)) {
        // Variable can be a string which then considered as a default
        // behavior.
        $ret = $prefixes;
      }
      else if (NULL !== $suffix && isset($prefixes[$suffix])) {
        if (FALSE !== $prefixes[$suffix]) {
          // If entry is set and not false an explicit prefix is set
          // for the bin.
          $ret = $prefixes[$suffix];
        }
        else {
          // If we have an explicit false it means no prefix whatever
          // is the default configuration.
          $ret = '';
        }
      }
      else {
        // Key is not set, we can safely rely on default behavior.
        if (isset($prefixes['default']) && FALSE !== $prefixes['default']) {
          $ret = $prefixes['default'];
        }
        else {
          // When default is not set or an explicit false this means
          // no prefix.
          $ret = '';
        }
      }
    }

    if (empty($ret)) {
      // If no prefix is given, use the same logic as core for APCu caching.
      $ret = Settings::getApcuPrefix('redis', DRUPAL_ROOT);
    }

    if (isset($_SERVER['MAIN_SITE_NAME'])) {
      $ret = md5(preg_replace('`^www\.`', '', $_SERVER['MAIN_SITE_NAME'])) . '_m_';
    }
    elseif (isset($_SERVER['SERVER_NAME'])) {
      $ret = md5(preg_replace('`^www\.`', '', $_SERVER['SERVER_NAME'])) . '_n_';
    }
    elseif (isset($_SERVER['HTTP_HOST'])) {
      $ret = md5(preg_replace('`^www\.`', '', $_SERVER['HTTP_HOST'])) . '_h_';
    }

    return $ret;
  }

  /**
   * Set prefix
   *
   * @param string $prefix
   */
  public function setPrefix($prefix) {
    $this->prefix = $prefix;
  }

  /**
   * Get prefix
   *
   * @return string
   */
  protected function getPrefix() {
    if (!isset($this->prefix)) {
      $this->prefix = $this->getDefaultPrefix();
    }
    return $this->prefix;
  }

}
