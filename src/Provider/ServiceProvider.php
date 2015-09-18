<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\Session;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Database\Schema;
use Bolt\Extension\Bolt\ClientLogin\Feedback;
use Silex\Application;
use Silex\ServiceProviderInterface;

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
                    $app['session'],
                    $app['request_stack'],
                    $app['logger.system']
                );
            }
        );

        $app['clientlogin.handler.local'] = $app->share(
            function ($app) {
                return new Handler\Local($app, $app['request_stack']);
            }
        );

        $app['clientlogin.handler.remote'] = $app->share(
            function ($app) {
                return new Handler\Remote($app, $app['request_stack']);
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

        $app['clientlogin.feedback'] = $app->share(
            function ($app) {
                $feedback = new Feedback($app['session']);
                $app->after([$feedback, 'after']);

                return $feedback;
            }
        );

        $app['clientlogin.config'] = $app->share(
            function ($this) {
                return new Config($this->config);
            }
        );

        $app['clientlogin.guzzle'] = $app->share(
            function ($app) {
                // We're needed, pop the pimple.
                $app['clientlogin.guzzle.loader'] = $app['clientlogin.guzzle.loader'];

                return new \GuzzleHttp\Client();
            }
        );

        $app['clientlogin.guzzle.loader'] = $app->share(
            function () {
                $baseDir = dirname(dirname(__DIR__));

                require $baseDir . '/lib/GuzzleHttp/Guzzle/functions_include.php';
                require $baseDir . '/lib/GuzzleHttp/Promise/functions_include.php';
                require $baseDir . '/lib/GuzzleHttp/Psr7/functions_include.php';

                $loader = new \Composer\Autoload\ClassLoader();
                $loader->setPsr4('GuzzleHttp\\', [
                    $baseDir . '/lib/GuzzleHttp/Guzzle',
                    $baseDir . '/lib/GuzzleHttp/Promise',
                    $baseDir . '/lib/GuzzleHttp/Psr7',
                ]);
                $loader->setPsr4('GuzzleHttp\\Promise\\', [
                    $baseDir . '/lib/GuzzleHttp/Promise',
                ]);
                $loader->setPsr4('GuzzleHttp\\Psr7\\', [
                    $baseDir . '/lib/GuzzleHttp/Psr7',
                ]);
                $loader->register(true);
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
