<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use League\OAuth2\Client\Entity\User;

/**
 * Client details class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ClientDetails
{
    /** @var mixed  */
    public $client = false;

    public function __construct()
    {
    }

    /**
     * Add an OAuth2 client data
     *
     * @param \League\OAuth2\Client\Entity\User $client
     */
    public function addOAuth2Client(User $client)
    {
        $this->client = $client;
    }

    /**
     * Add a password based user data
     *
     * @param \stdClass $client
     */
    public function addPasswordClient(\stdClass $client)
    {
        $this->client = $client;
    }

    /**
     * Getter for client data
     *
     * @return Ambigous <boolean, User, stdClass>
     */
    public function getClient()
    {
        return $this->client;
    }
}
