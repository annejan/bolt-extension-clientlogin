<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer\Storage;

use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\RefreshTokenInterface;

/**
 * Methods for retrieving, creating and deleting refresh tokens.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RefreshTokenStorage implements RefreshTokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $accessToken)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete(RefreshTokenEntity $token)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get($token)
    {
    }
}
