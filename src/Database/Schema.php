<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database;

use Bolt\Extension\Bolt\ClientLogin\Client;
use League\OAuth2\Client\Token\AccessToken;
use Silex\Application;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * ClientLogin database schema
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Schema
{
    /** @var \Bolt\Storage\Database\Schema\Manager */
    private $schemaManager;
    /** @var string */
    private $profileTableName;
    /** @var string */
    private $sessionTableName;

    /**
     * Constructor
     *
     * @param mixed $schemaManager
     *
     * Note that $schemaManager will be either:
     * \Bolt\Storage\Database\Schema\Manager
     * \Bolt\Database\IntegrityChecker
     *
     */
    public function __construct($schemaManager, $profileTableName, $sessionTableName)
    {
        $this->schemaManager = $schemaManager;
        $this->profileTableName = $profileTableName;
        $this->sessionTableName = $sessionTableName;
    }

    /**
     * Create/update database tables
     *
     * @param string $profileTableName
     * @param string $sessionTableName
     */
    public function build()
    {
        // User/client provider table
        $this->schemaManager->registerExtensionTable(
            function ($schema) use ($this) {
                $table = $schema->createTable($this->profileTableName);
                $table->addColumn('id',           'integer', ['autoincrement' => true]);
                $table->addColumn('provider',     'string',  ['length' => 64]);
                $table->addColumn('identifier',   'string',  ['length' => 128]);
                $table->addColumn('username',     'string',  ['length' => 64]);
                $table->addColumn('enabled',      'boolean', []);
                $table->addColumn('providerdata', 'text');
                $table->addColumn('sessiondata',  'text');
                $table->addColumn('lastupdate',   'datetime');

                $table->setPrimaryKey(['id']);

                $table->addIndex(['provider']);
                $table->addIndex(['identifier']);
                $table->addIndex(['username']);

                return $table;
            }
        );

        // User/client session table
        $this->schemaManager->registerExtensionTable(
            function ($schema) use ($this) {
                $table = $schema->createTable($this->sessionTableName);
                $table->addColumn('id',       'integer', ['autoincrement' => true]);
                $table->addColumn('userid',   'integer');
                $table->addColumn('session',  'string', ['length' => 64]);
                $table->addColumn('token',    'text',   ['notnull' => false, 'default' => null]);
                $table->addColumn('lastseen', 'datetime');

                $table->setPrimaryKey(['id']);

                $table->addIndex(['userid']);
                $table->addIndex(['session']);

                return $table;
            }
        );
    }
}
