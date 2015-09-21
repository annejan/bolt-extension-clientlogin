<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\SessionManager;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Database\Schema;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Bolt\Extension\Bolt\ClientLogin\Feedback;
use Bolt\Extension\Bolt\ClientLogin\OAuth2\Provider;
use Bolt\Extension\Bolt\ClientLogin\OAuth2\ProviderManager;
use Bolt\Extension\Bolt\ClientLogin\Twig\Helper\UserInterface;
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

    public function boot(Application $app)
    {
    }

    public function register(Application $app)
    {
        $tablePrefix = rtrim($app['config']->get('general/database/prefix', 'bolt_'), '_') . '_';
        $app['clientlogin.db.table'] = $tablePrefix . 'clientlogin';

        $app['clientlogin.session'] = $app->share(
            function ($app) {
                return new SessionManager(
                    $app['clientlogin.records'],
                    $app['session'],
                    $app['request_stack'],
                    $app['logger.system']
                );
            }
        );

        $app['clientlogin.handler'] = $app->share(
            function () {
                throw new \RuntimeException('ClientLogin authentication handler not set up!');
            }
        );

        $app['clientlogin.handler.local'] = $app->protect(
            function ($app) {
                return new Handler\Local($app, $app['request_stack']);
            }
        );

        $app['clientlogin.handler.remote'] = $app->protect(
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

        $app['clientlogin.ui'] = $app->share(
            function ($app) {
                return new UserInterface($app);
            }
        );

        $app['clientlogin.config'] = $app->share(
            function ($this) {
                return new Config($this->config);
            }
        );

        //
        $app['clientlogin.provider.manager'] = $app->share(
            function ($app) {
                $rootUrl = $app['resources']->getUrl('rooturl');

                return new ProviderManager($app['clientlogin.config'], $app['clientlogin.guzzle'], $app['logger.system'], $rootUrl);
            }
        );

        // This will become the active provider during the request cycle
        $app['clientlogin.provider'] = $app->share(
            function () {
                throw new \RuntimeException('ClientLogin authentication provider not set up!');
            }
        );

        // A generic provider
        $app['clientlogin.provider.generic'] = $app->protect(
            function () {
                return new Provider\Generic([]);
            }
        );

        // Build provider closures for each enabled provider
        foreach ($this->config['providers'] as $providerName => $providerConfig) {
            if ($providerConfig['enabled'] === true) {
                $app['clientlogin.provider.' . strtolower($providerName)] = $app->protect(
                    function ($app) use ($providerName) {
                        return $app['clientlogin.provider.manager']->getProvider($providerName);
                    }
                );
            }
        }

        /** @deprecated Temporary workaround until Bolt core can update to Guzzle 6. */
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
}
