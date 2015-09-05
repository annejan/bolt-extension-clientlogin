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
     * Get the profile query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Profile
     */
    public function getProfileQuery()
    {
        return new Query\Session($this->db, $this->profileTableName);
    }

    /**
     * Get the session query builder.
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Database\Query\Session
     */
    public function getSessionQuery()
    {
        return new Query\Session($this->db, $this->sessionTableName);
    }

    /**
     * Execute a query and fetch the result as an associative array.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder
     *
     * @return array|false|null
     */
    private function fetchArray(QueryBuilder $query)
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
