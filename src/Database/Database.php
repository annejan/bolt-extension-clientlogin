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
}
