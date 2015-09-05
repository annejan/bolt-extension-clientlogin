<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database;

use Bolt\Extension\Bolt\ClientLogin\Client;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Silex\Application;

/**
 * Authenticated user record maintenance
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Database
{
    /** @var \Doctrine\DBAL\Connection */
    protected $db;
    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    protected $config;

    /** @var string */
    private $profileTableName;
    /** @var string */
    private $sessionTableName;

    /**
     * Constructor.
     *
     * @param Connection      $db
     * @param Config          $config
     * @param LoggerInterface $logger
     * @param string          $profileTableName
     * @param string          $sessionTableName
     */
    public function __construct(
        Connection $db,
        Config $config,
        LoggerInterface $logger,
        $profileTableName,
        $sessionTableName
    )
    {
        $this->db = $db;
        $this->config = $config;
        $this->logger = $logger;
        $this->profileTableName = $profileTableName;
        $this->sessionTableName = $sessionTableName;
    }

    /**
     * Get a session record by session ID.
     *
     * @return array|false|null
     */
    public function getSessionBySessionId($sessionId)
    {
        $query = $this->getSessionQuery()
            ->queryFetchBySessionId()
            ->setParameter(':session', $sessionId);

        return $this->fetchArray($query);
    }

    /**
     * Get a session record by user ID.
     *
     * @return array|false|null
     */
    public function getSessionByUserId($userId)
    {
        $query = $this->getSessionQuery()
            ->queryFetchByUserId()
            ->setParameter(':userid', $userId);

        return $this->fetchArray($query);
    }

    /**
     * Delete a single session record.
     *
     * @param string $sessionId
     */
    public function deleteSession($sessionId)
    {
        $query = $this->getSessionQuery()
            ->queryDeleteSession()
            ->setParameter(':session', $sessionId);
        $this->executeQuery($query);
    }

    /**
     * Delete expired session records.
     *
     * @param string $sessionId
     */
    public function deleteSessionsExpired($sessionId)
    {
        $maxage = $this->config->get('login_expiry');
        $query = $this->getSessionQuery()
            ->queryDeleteExpiredSessions()
            ->setParameter(':maxage', date('Y-m-d H:i:s', strtotime("-$maxage days")));
        $this->executeQuery($query);
    }

    /**
     * Insert a session record.
     *
     * @param string $userId
     * @param string $sessionId
     * @param object $token
     */
    public function insertSession($userId, $sessionId, $token)
    {
        $query = $this->getSessionQuery()
            ->insertSession()
            ->setParameters([
                ':userid'     => $userId,
                ':session'    => $sessionId,
                ':token'      => json_encode($token),
                ':lastupdate' => date('Y-m-d H:i:s', time()),
            ]);
        $this->executeQuery($query);
    }

    /**
     * Get the profile query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Profile
     */
    protected function getProfileQuery()
    {
        return new Query\Session($this->db, $this->profileTableName);
    }

    /**
     * Get the session query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Session
     */
    protected function getSessionQuery()
    {
        return new Query\Session($this->db, $this->sessionTableName);
    }

    /**
     * Execute a query.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder
     *
     * @return array|false|null
     */
    protected function executeQuery(QueryBuilder $query)
    {
        try {
            return $query->execute();
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->logger->critical("ClientLogin had an database exception.", ['event' => 'exception', 'exception' => $e]);
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
        try {
            return $query
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC);
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->logger->critical("ClientLogin had an database exception.", ['event' => 'exception', 'exception' => $e]);
        }
    }
}
