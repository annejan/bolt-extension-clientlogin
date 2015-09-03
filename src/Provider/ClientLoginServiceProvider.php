<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ServiceProviderInterface;

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
        $app['clientlogin.session'] = $app->share(
            function ($app) {
                $session = new Session($app);

                return $session;
            }
        );

        $app['clientlogin.db'] = $app->share(
            function ($app) {
                $records = new Database($app);

                return $records;
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
