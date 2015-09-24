<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer\Storage;

use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AuthCodeInterface;

/**
 * Methods for retrieving, creating and deleting authorization codes.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AuthCodeStorage implements AuthCodeInterface
{
    /**
     * {@inheritdoc}
     */
    public function associateScope(AuthCodeEntity $token, ScopeEntity $scope)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $sessionId, $redirectUri)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AuthCodeEntity $token)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get($code)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(AuthCodeEntity $token)
    {
    }
}
