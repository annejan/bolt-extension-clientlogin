<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Token;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

/**
 * Authenticated user record maintenance
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RecordManager
{
    /** @var \Doctrine\DBAL\Connection */
    protected $db;
    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    protected $config;

    /** @var string */
    private $tableName;

    /**
     * Constructor.
     *
     * @param Connection      $db
     * @param Config          $config
     * @param LoggerInterface $logger
     * @param string          $tableName
     */
    public function __construct(
        Connection $db,
        Config $config,
        LoggerInterface $logger,
        $tableName
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->logger = $logger;
        $this->tableName = $tableName;
    }

    /**
     * Get a profile record by ID.
     *
     * @param integer $id
     *
     * @return array|false
     */
    public function getProfileById($id)
    {
        $query = $this->getProfileQueriesRead()->queryFetchById($id);

        return $this->fetchArray($query);
    }

    /**
     * Get a profile record by access token ID.
     *
     * @param string $tokenId
     *
     * @return array|false
     */
    public function getProfileByResourceOwnerId($provider, $resourceOwnerId)
    {
        $query = $this->getProfileQueriesRead()->queryFetchByResourceOwnerId($provider, $resourceOwnerId);

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
    public function getProfileByResourceOwnerId($provider, $resourceOwnerId)
    {
        $query = $this->getProfileQueriesRead()->queryFetchByResourceOwnerId($provider, $resourceOwnerId);

        return $this->fetchArray($query);
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
    public function insertProfile($provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        $query = $this->getProfileQueriesWrite()->queryInsert($provider, $resourceOwner->getId(), $accessToken, $resourceOwner);

        return $this->executeQuery($query);
    }

    /**
     * Update a user profile.
     *
     * @param string                 $provider
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    public function updateProfile($provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        $query = $this->getProfileQueriesWrite()->queryUpdate($provider, $resourceOwner->getId(), $accessToken, $resourceOwner);

        return $this->executeQuery($query);
    }

    /**
     * Update a user access token.
     *
     * @param string      $provider
     * @param AccessToken $accessToken
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    public function updateAccessToken($provider, AccessToken $accessToken)
    {
        $query = $this->getProfileQueriesWrite()
            ->queryUpdateAccessToken()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $accessToken->getResourceOwnerId(),
                'access_token'      => (string) $accessToken,
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
            ]);

        return $this->executeQuery($query);
    }

    /**
     * Update a user access token.
     *
     * @param string      $provider
     * @param AccessToken $accessToken
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    public function updateRefreshToken($provider, AccessToken $accessToken)
    {
        $query = $this->getProfileQueriesWrite()
            ->queryUpdateAccessToken()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $accessToken->getResourceOwnerId(),
                'refresh_token'     => $accessToken->getRefreshToken(),
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
            ]);

        return $this->executeQuery($query);
    }

    /**
     * Delete profile record.
     *
     * @param string $provider
     * @param string $resourceOwnerId
     *
     * @return array|false
     */
    public function deleteProfileByResource($provider, $resourceOwnerId)
    {
        $query = $this->getProfileQueriesDelete()->queryDelete(provider, $resourceOwnerId);

        return $this->executeQuery($query);
    }

    /**
     * Get the table name.
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Get the profile read query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\ProfileRead
     */
    protected function getProfileQueriesRead()
    {
        return new Query\ProfileRead($this->db, $this->tableName);
    }

    /**
     * Get the profile remove query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\ProfileDelete
     */
    protected function getProfileQueriesDelete()
    {
        return new Query\ProfileDelete($this->db, $this->tableName);
    }

    /**
     * Get the profile write query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\ProfileWrite
     */
    protected function getProfileQueriesWrite()
    {
        return new Query\ProfileWrite($this->db, $this->tableName);
    }

    /**
     * Get the session read query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\SessionRead
     */
    protected function getSessionQueriesRead()
    {
        return new Query\SessionRead($this->db, $this->tableName . '_sessions');
    }

    /**
     * Get the session remove query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\SessionDelete
     */
    protected function getSessionQueriesDelete()
    {
        return new Query\SessionDelete($this->db, $this->tableName . '_sessions');
    }

    /**
     * Get the session write query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\SessionWrite
     */
    protected function getSessionQueriesWrite()
    {
        return new Query\SessionWrite($this->db, $this->tableName . '_sessions');
    }

    /**
     * Execute a query.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder
     *
     * @return \Doctrine\DBAL\Driver\Statement|integer|null
     */
    protected function executeQuery(QueryBuilder $query)
    {
        return $query->execute();
        try {
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->logger->critical('ClientLogin had a database exception.', ['event' => 'exception', 'exception' => $e]);
        }
    }

    /**
     * Execute a query and fetch the result as an associative array.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder
     *
     * @return array|false|null
     */
    protected function fetchArray(QueryBuilder $query)
    {
        return $query
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC);
        try {
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->logger->critical('ClientLogin had a database exception.', ['event' => 'exception', 'exception' => $e]);
        }
    }
}
