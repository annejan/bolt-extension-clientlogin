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
        $query = $this->getProfileQueriesRead()
            ->queryFetchById()
            ->setParameter(':id', $id);

        return $this->fetchArray($query);
    }

    /**
     * Get a profile record by access token ID.
     *
     * @param string $tokenId
     *
     * @return array|false
     */
    public function getProfileByAccessToken($tokenId)
    {
        $query = $this->getProfileQueriesRead()
            ->queryFetchByAccessToken()
            ->setParameter(':access_token', $tokenId);

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
    public function getProfileByResource($provider, $resourceOwnerId)
    {
        $query = $this->getProfileQueriesRead()
            ->queryFetchByResource()
            ->setParameter(':provider', $provider)
            ->setParameter(':resource_owner_id', $resourceOwnerId);

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
        $query = $this->getProfileQueriesWrite()
            ->queryInsert()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwner->getId(),
//                 'access_token'      => (string) $accessToken,
                'refresh_token'     => $accessToken->getRefreshToken(),
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
            ]);

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
        $query = $this->getProfileQueriesWrite()
            ->queryUpdate()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwner->getId(),
                'access_token'      => (string) $accessToken,
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
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
        $query = $this->getProfileQueriesRemove()
            ->queryDelete()
            ->setParameter(':provider', $provider)
            ->setParameter(':resource_owner_id', $resourceOwnerId);

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
     * Get the read query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Profile
     */
    protected function getProfileQueriesRead()
    {
        return new Query\ProfileRead($this->db, $this->tableName);
    }

    /**
     * Get the remove query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Profile
     */
    protected function getProfileQueriesRemove()
    {
        return new Query\ProfileRemove($this->db, $this->tableName);
    }

    /**
     * Get the write query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Profile
     */
    protected function getProfileQueriesWrite()
    {
        return new Query\ProfileWrite($this->db, $this->tableName);
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
