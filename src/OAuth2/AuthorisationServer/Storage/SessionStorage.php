<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer\Storage;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\SessionInterface;

/**
 * Methods for retrieving and setting sessions.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SessionStorage implements SessionInterface
{
    /**
     * {@inheritdoc}
     */
    public function associateScope(SessionEntity $session, ScopeEntity $scope)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create($ownerType, $ownerId, $clientId, $clientRedirectUri = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getByAccessToken(AccessTokenEntity $accessToken)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getByAuthCode(AuthCodeEntity $authCode)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(SessionEntity $session)
    {
    }
}
