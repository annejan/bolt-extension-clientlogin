<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer\Storage;

use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\ClientInterface;

/**
 * Single method to get a client.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ClientStorage implements ClientInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBySession(SessionEntity $session)
    {
    }
}
