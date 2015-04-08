<?php

abstract class Redis_AbstractBackend
{
    /**
     * Key components name separator
     */
    const KEY_SEPARATOR = ':';

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $namespace;

    /**
     * Default constructor
     */
    public function __construct($namespace = null, $prefix = null)
    {
        if (null === $prefix) {
            $this->prefix = $prefix = Redis_Client::getDefaultPrefix($namespace);
        } else {
            $this->prefix = $prefix;
        }

        if (null !== $namespace) {
            $this->namespace = $namespace;
        }
    }

    /**
     * Set prefix
     *
     * @param string $prefix
     */
    final public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Get prefix
     *
     * @return string
     */
    final public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set namespace
     *
     * @param string $namespace
     */
    final public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Get namespace
     *
     * @return string
     */
    final public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get full key name using the set prefix
     *
     * @param string ...
     *   Any numer of strings to append to path using the separator
     *
     * @return string
     */
    public function getKey()
    {
        $args = array_filter(func_get_args());

        if ($this->namespace) {
            array_unshift($args, $this->namespace);
        }
        if ($this->prefix) {
            array_unshift($args, $this->prefix);
        }

        return implode(self::KEY_SEPARATOR, $args);
    }
}
