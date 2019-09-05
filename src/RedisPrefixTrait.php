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

    $ret = ''; // Ignore prefix defined in global.inc or local.settings.php

    if (isset($_SERVER['MAIN_SITE_NAME'])) {
      //$ret = md5(preg_replace('`^www\.`', '', $_SERVER['MAIN_SITE_NAME'])) . '_y_';
      $ret = preg_replace('`^www\.`', '', $_SERVER['MAIN_SITE_NAME']) . '_y_';
    }
    elseif (isset($_SERVER['SERVER_NAME'])) {
      //$ret = md5(preg_replace('`^www\.`', '', $_SERVER['SERVER_NAME'])) . '_n_';
      $ret = preg_replace('`^www\.`', '', $_SERVER['SERVER_NAME']) . '_n_';
    }
    elseif (isset($_SERVER['HTTP_HOST'])) {
      //$ret = md5(preg_replace('`^www\.`', '', $_SERVER['HTTP_HOST'])) . '_h_';
      $ret = preg_replace('`^www\.`', '', $_SERVER['HTTP_HOST']) . '_h_';
    }

    if (empty($ret)) {
      // If still no prefix is given, use the same logic as core for APCu caching.
      $ret = Settings::getApcuPrefix('redis', DRUPAL_ROOT);
    }

    return $ret;
  }

  /**
   * Set prefix
   *
   * @param string $prefix
   */
  public function setPrefix($prefix) {
    if (!isset($prefix)) {
      $this->prefix = $this->getDefaultPrefix();
    }
    else {
      $this->prefix = $prefix;
    }
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
