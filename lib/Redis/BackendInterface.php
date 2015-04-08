<?php

/**
 * Client based Redis component
 */
interface Redis_BackendInterface
{
    /**
     * Set client
     *
     * @param mixed $client
     */
    public function setClient($client);

    /**
     * Get client
     *
     * @return mixed
     */
    public function getClient();
}
