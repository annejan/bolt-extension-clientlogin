<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Authenticated user record maintenance
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RecordManager extends RecordManagerBase
{
    /**
     * Get a profile record by GUID.
     *
     * @param integer $guid
     *
     * @return array|false
     */
    public function getProfile($guid)
    {
        $query = $this->getProviderQueriesRead()->queryFetchById($guid);

        return $this->fetchArray($query);
    }

    /**
     * Get session records by GUID.
     *
     * @param string $guid
     *
     * @return array|false
     */
    public function getProviderSessions($guid)
    {
        $query = $this->getSessionQueriesRead()->queryFetchByGuid($guid);

        return $this->fetchArray($query);
    }

    /**
     * Get session record by access token ID.
     *
     * @param string $accessTokenId
     *
     * @return array|false
     */
    public function getProviderByAccessTokenId($accessTokenId)
    {
        $query = $this->getSessionQueriesRead()->queryFetchByAccessToken($accessTokenId);

        return $this->fetchArray($query);
    }

    /**
     * Get a profile record by provider and resource owner ID.
     *
     * @param string $provider
     * @param string $resourceOwnerId
     *
     * @return array|false
     */
    public function getProviderByResourceOwnerId($provider, $resourceOwnerId)
    {
        $query = $this->getProviderQueriesRead()->queryFetchByResourceOwnerId($provider, $resourceOwnerId);

        return $this->fetchArray($query);
    }

    /**
     * Insert or update a user profile.
     *
     * @param string                 $guid
     * @param string                 $provider
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    public function writeProvider($guid, $provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        if ($guid === 'insert') {
            return $this->insertProvider($provider, $accessToken, $resourceOwner);
        } else {
            return $this->updateProvider($guid, $provider, $accessToken, $resourceOwner);
        }
    }

    /**
     * Insert a user profile.
     *
     * @param string                 $provider
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    protected function insertProvider($provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        $resourceOwnerId = $resourceOwner->getId();
        $query = $this->getProviderQueriesWrite()->queryInsert($provider, $resourceOwnerId, $accessToken, $resourceOwner);

        return $this->executeQuery($query);
    }

    /**
     * Update a user profile.
     *
     * @param string                 $guid
     * @param string                 $provider
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    protected function updateProvider($guid, $provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        $resourceOwnerId = $resourceOwner->getId();
        $query = $this->getProviderQueriesWrite()->queryUpdate($provider, $resourceOwnerId, $accessToken, $resourceOwner);

        return $this->executeQuery($query);
    }

    /**
     * Insert or update a session record for a user's access token.
     *
     * @param string      $guid
     * @param string      $provider
     * @param AccessToken $accessToken
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    public function writeSession($guid, $provider, AccessToken $accessToken)
    {
        $session = $this->getProviderSessions($guid);

        if ($session) {
            $query = $this->getSessionQueriesWrite()->queryUpdate($accessToken);
        } else {
            $query = $this->getSessionQueriesWrite()->queryInsert($guid, $accessToken);
        }

        return $this->executeQuery($query);
    }
}
