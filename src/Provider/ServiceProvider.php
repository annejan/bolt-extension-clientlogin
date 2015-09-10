<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Bolt\Extension\Bolt\ClientLogin\Database\Schema;

class ServiceProvider implements ServiceProviderInterface
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
        $app['clientlogin.db.table'] = $tablePrefix . 'clientlogin';

        $app['clientlogin.session'] = $app->share(
            function ($app) {
                return new Session(
                    $app['clientlogin.records'],
                    $app['request_stack'],
                    $app['logger.system']
                );
            }
        );

        $app['clientlogin.records'] = $app->share(
            function ($app) {
                $records = new RecordManager(
                    $app['db'],
                    $app['clientlogin.config'],
                    $app['logger.system'],
                    $app['clientlogin.db.table']
                );

                return $records;
            }
        );

        $app['clientlogin.db.schema'] = $app->share(
            function ($app) {
                $schema = new Schema(
                    $app['integritychecker'],
                    $app['clientlogin.db.table']
                );

                return $schema;
            }
        );

        $app['clientlogin.config'] = $app->share(
            function ($this) {
                return new Config($this->config);
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
