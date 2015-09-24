<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer\Storage;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AccessTokenInterface;

/**
 * Methods for retrieving, creating and deleting access tokens.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AccessTokenStorage implements AccessTokenInterface
{
    /**
     * {@inheridoc}
     */
    public function associateScope(AccessTokenEntity $token, ScopeEntity $scope)
    {
    }

    /**
     * {@inheridoc}
     */
    public function create($token, $expireTime, $sessionId)
    {
    }

    /**
     * {@inheridoc}
     */
    public function delete(AccessTokenEntity $token)
    {
    }

    /**
     * {@inheridoc}
     */
    public function get($token)
    {
    }

    /**
     * {@inheridoc}
     */
    public function getScopes(AccessTokenEntity $token)
    {
    }
}
