<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database;

use Doctrine\DBAL\Schema\Schema as DbalSchema;

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
    private $tableName;

    /**
     * Constructor
     *
     * @param mixed $schemaManager
     *
     * Note that $schemaManager will be either:
     * \Bolt\Storage\Database\Schema\Manager
     * \Bolt\Database\IntegrityChecker
     */
    public function __construct($schemaManager, $tableName)
    {
        $this->schemaManager = $schemaManager;
        $this->tableName = $tableName;
    }

    /**
     * Create/update database table.
     */
    public function build()
    {
        $tableName = $this->tableName;

        // User/client provider table
        $this->schemaManager->registerExtensionTable(
            function (DbalSchema $schema) use ($tableName) {
                $table = $schema->createTable($tableName);
                $table->addColumn('id',                'integer',  ['autoincrement' => true]);
                $table->addColumn('provider',          'string',   ['length' => 64]);
                $table->addColumn('resource_owner_id', 'string',   ['length' => 128]);
                $table->addColumn('access_token',      'string',   ['length' => 128]);
                $table->addColumn('refresh_token',     'string',   ['notnull' => false, 'default' => null, 'length' => 128, ]);
                $table->addColumn('expires',           'integer',  ['notnull' => false, 'default' => null]);
                $table->addColumn('lastupdate',        'datetime', ['notnull' => false, 'default' => null]);
                $table->addColumn('resource_owner',    'text',     ['notnull' => false, 'default' => null]);
                $table->addColumn('enabled',           'boolean',  ['default' => true]);

                $table->setPrimaryKey(['id']);

                $table->addIndex(['provider']);
                $table->addIndex(['resource_owner_id']);
                $table->addIndex(['access_token']);
                $table->addIndex(['refresh_token']);

                return $table;
            }
        );
    }
}
