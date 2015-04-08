<?php

// It may happen we get here with no autoloader set during the Drupal core
// early bootstrap phase, at cache backend init time.
if (!interface_exists('Redis_Client_FactoryInterface')) {
    require_once dirname(__FILE__) . '/Client/FactoryInterface.php';
    require_once dirname(__FILE__) . '/Client/Manager.php';
}

/**
 * This static class only reason to exist is to tie Drupal global
 * configuration to OOP driven code of this module: it will handle
 * everything that must be read from global configuration and let
 * other components live without any existence of it
 */
class Redis_Client
{
    /**
     * Cache implementation namespace.
     */
    const REDIS_IMPL_CACHE = 'Redis_Cache_';

    /**
     * Lock implementation namespace.
     */
    const REDIS_IMPL_LOCK = 'Redis_Lock_Backend_';

    /**
     * Cache implementation namespace.
     */
    const REDIS_IMPL_QUEUE = 'Redis_Queue_';

    /**
     * Session implementation namespace.
     */
    const REDIS_IMPL_SESSION = 'Redis_Session_Backend_';

    /**
     * Session implementation namespace.
     */
    const REDIS_IMPL_PATH = 'Redis_Path_';

    /**
     * Session implementation namespace.
     */
    const REDIS_IMPL_CLIENT = 'Redis_Client_';

    /**
     * @var Redis_Client_Manager
     */
    private static $manager;

    /**
     * Get client manager
     *
     * @return Redis_Client_Manager
     */
    static public function getManager()
    {
        global $conf;

        if (null === self::$manager) {

            $className = self::getClass(self::REDIS_IMPL_CLIENT);
            $factory = new $className();

            // Build server list from conf
            $serverList = array();
            if (isset($conf['redis_servers'])) {
                $serverList = $conf['redis_servers'];
            }
            // Backward configuration compatibility with older versions
            if (empty($serverList) || !isset($serverList['default'])) {
                foreach (array('host', 'port', 'base', 'password', 'socket') as $key) {
                    if (isset($conf['redis_client_' . $key])) {
                        $serverList[Redis_Client_Manager::REALM_DEFAULT][$key] = $conf['redis_client_' . $key];
                    }
                }
            }

            self::$manager = new Redis_Client_Manager($factory, $serverList);
        }

        return self::$manager;
    }

    /**
     * Find client class name
     *
     * @return string
     */
    static private function getClientInterfaceName()
    {
        global $conf;

        if (!empty($conf['redis_client_interface'])) {
            return $conf['redis_client_interface'];
        } else if (class_exists('Predis\Client')) {
            // Transparent and abitrary preference for Predis library.
            return  $conf['redis_client_interface'] = 'Predis';
        } else if (class_exists('Redis')) {
            // Fallback on PhpRedis if available.
            return $conf['redis_client_interface'] = 'PhpRedis';
        } else {
            throw new Exception("No client interface set.");
        }
    }

    /**
     * Get the client for the 'default' realm
     *
     * @return mixed
     *
     * @deprecated
     */
    public static function getClient()
    {
        return self::getManager()->getClient();
    }

    /**
     * Get specific class implementing the current client usage for the specific
     * asked core subsystem.
     * 
     * @param string $system
     *   One of the Redis_Client::IMPL_* constant.
     * @param string $clientName
     *   Client name, if fixed.
     * 
     * @return string
     *   Class name, if found.
     *
     * @deprecated
     */
    static public function getClass($system)
    {
        $class = $system . self::getClientInterfaceName();

        if (!class_exists($class)) {
            throw new Exception(sprintf("Class '%s' does not exist", $class));
        }

        return $class;
    }
}

