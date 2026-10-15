<?php

namespace Drupal\redis;

use Drupal\Core\Database\Database;
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

    if (isset($_SERVER['SERVER_NAME'])) {
      $ret = md5(preg_replace('`^www\.`', '', $_SERVER['SERVER_NAME'])) . '_sn_' . $this->getSiteCopySegment();
    }
    elseif (isset($_SERVER['HTTP_HOST'])) {
      $ret = md5(preg_replace('`^www\.`', '', $_SERVER['HTTP_HOST'])) . '_hh_' . $this->getSiteCopySegment();
    }

    if (empty($ret)) {
      // If still no prefix is given, use the same logic as core for APCu caching.
      $ret = Settings::getApcuPrefix('redis', DRUPAL_ROOT);
    }

    return $ret;
  }

  /**
   * Get the segment that tells copies of one site apart
   *
   * Every copy of a site under one name, its www. twin included, has the same
   * name-based prefix: a Migrate builds its copy beside the live site, and the
   * two may run cores whose cached objects do not load in each other. Each
   * copy has a database of its own, so its connection names its namespace.
   * Empty while no database is known (an install before its database exists),
   * which keeps the name-based prefix. The leading md5 of the name stays, so a
   * purge by name still reaches every copy.
   *
   * @return string
   */
  protected function getSiteCopySegment() {
    $info = Database::getConnectionInfo('default');
    if (empty($info['default']['database'])) {
      return '';
    }
    $db = $info['default'];
    $prefix = (isset($db['prefix']) && is_string($db['prefix'])) ? $db['prefix'] : '';
    $key = strtolower(trim((string) ($db['host'] ?? ''))) . ':' . trim((string) ($db['port'] ?? ''))
      . '/' . $db['database'] . '/' . $prefix;
    return substr(md5($key), 0, 8) . '_';
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
