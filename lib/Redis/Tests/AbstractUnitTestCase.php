<?php

abstract class Redis_Tests_AbstractUnitTestCase extends DrupalUnitTestCase
{
    /**
     * @var boolean
     */
    static protected $loaderEnabled = false;

    /**
     * Enable the autoloader
     *
     * This exists in this class in case the autoloader is not set into the
     * settings.php file or another way
     *
     * @return void|boolean
     */
    static protected function enableAutoload()
    {
        if (self::$loaderEnabled) {
            return;
        }
        if (class_exists('Redis_Client')) {
            return;
        }

        spl_autoload_register(function ($className) {
            $parts = explode('_', $className);
            if ('Redis' === $parts[0]) {
                $filename = __DIR__ . '/../lib/' . implode('/', $parts) . '.php';
                return (bool) include_once $filename;
            }
            return false;
        }, null, true);

        self::$loaderEnabled = true;
    }

    /**
     * Set up the Redis configuration.
     *
     * Set up the needed variables using variable_set() if necessary.
     *
     * @return string
     *   Client interface or null if not exists
     */
    abstract protected function getClientInterface();

    /**
     * Reset and prepare client manager
     */
    final protected function prepareClientManager()
    {
        $interface = $this->getClientInterface();

        if (null === $interface) {
            throw new \Exception("Test skipped due to missing driver");
        }

        $GLOBALS['conf']['redis_client_interface'] = $interface;
        Redis_Client::reset();
    }

    public function setUp()
    {
        self::enableAutoload();
        $this->prepareClientManager();
        parent::setUp();
        drupal_install_schema('system');
        drupal_install_schema('locale');
    }

    public function tearDown()
    {
        drupal_uninstall_schema('locale');
        drupal_uninstall_schema('system');
        Redis_Client::reset();
        parent::tearDown();
    }
}
