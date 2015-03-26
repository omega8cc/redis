<?php

/**
 * Client pool manager for multi-server configurations
 */
class Redis_Client_Manager
{
    /**
     * Redis default host
     */
    const REDIS_DEFAULT_HOST = "127.0.0.1";

    /**
     * Redis default port
     */
    const REDIS_DEFAULT_PORT = 6379;

    /**
     * Redis default socket (will override host and port)
     */
    const REDIS_DEFAULT_SOCKET = null;

    /**
     * Redis default database: will select none (Database 0)
     */
    const REDIS_DEFAULT_BASE = null;

    /**
     * Redis default password: will not authenticate
     */
    const REDIS_DEFAULT_PASSWORD = null;

    /**
     * Default realm
     */
    const REALM_DEFAULT = 'default';

    /**
     * @var Redis_Client_Interface[]
     */
    private $clients;

    /**
     * Get client for the given realm
     *
     * @param string $realm
     * @param boolean $allowDefault
     */
    public function getClient($realm = self::REALM_DEFAULT, $allowDefault = true)
    {
        if (!isset($this->clients[$realm])) {
            $client = $this->createClient($realm);

            if (false === $client) {
                // @todo
            }
        }

        return $this->clients[$realm];
    }

    /**
     * Build connection parameters array from current Drupal settings
     *
     * @param string $realm
     *
     * @return boolean|string[]
     *   A key-value pairs of configuration values or false if realm is
     *   not defined per-configuration
     */
    private function buildOptions($realm)
    {
        global $conf;

        $info = null;

        if (isset($conf['redis_servers'])) {
            if (isset($conf['redis_servers'][$realm])) {
                $info = $conf['redis_servers'][$realm];
            } else {
                return false;
            }
        }

        if (null === $info && self::REALM_DEFAULT === $realm) {
            // Backward configuration compatibility with older version
            $info = array();
            foreach (array('host', 'port', 'base', 'password', 'socket') as $key) {
                if (isset($conf['redis_client_' . $key])) {
                    $info[$key] = $conf['redis_client_' . $key];
                }
            }
        }

        $info += array(
            'host'     => self::REDIS_DEFAULT_HOST,
            'port'     => self::REDIS_DEFAULT_PORT,
            'base'     => self::REDIS_DEFAULT_BASE,
            'password' => self::REDIS_DEFAULT_PASSWORD,
            'socket'   => self::REDIS_DEFAULT_SOCKET
        );

        return $info;
    }

    /**
     * Get client singleton
     */
    private function createClient($realm)
    {
        $info = $this->buildOptions($realm);

        if (false === $info) {
            
        }
    }
}
