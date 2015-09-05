<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\Database;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Bolt\Extension\Bolt\ClientLogin\Database\Schema;

class ClientLoginServiceProvider implements ServiceProviderInterface
{
    /** @var array */
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function register(Application $app)
    {
        $tablePrefix = rtrim($app['config']->get('general/database/prefix', 'bolt_'), '_') . '_';
        $userTable = $tablePrefix . 'client_profiles';
        $sessionTable = $tablePrefix . 'client_sessions';

        $app['clientlogin.session'] = $app->share(
            function ($app) {
                $session = new Session($app);

                return $session;
            }
        );

        $app['clientlogin.db'] = $app->share(
            function ($app) use ($userTable, $sessionTable) {
                $records = new Database(
                    $app['db'],
                    $app['clientlogin.config'],
                    $app['logger.system'],
                    $userTable,
                    $sessionTable
                );

                return $records;
            }
        );

        $app['clientlogin.db.schema'] = $app->share(
            function ($app) use ($userTable, $sessionTable) {
                $schema = new Schema(
                    $app['integritychecker'],
                    $userTable,
                    $sessionTable
                );

                return $schema;
            }
        );

        $app['clientlogin.config'] = $app->share(
            function ($app) {
                return new Config($this->config);
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
