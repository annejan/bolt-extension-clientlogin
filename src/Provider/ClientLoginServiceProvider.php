<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\ClientRecords;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Symfony\Component\HttpFoundation\Session\Session as SessionHandler;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ClientLoginServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['clientlogin.session'] = $app->share(
            function ($app) {
                $session = new Session($app);

                return $session;
            }
        );

        $app['clientlogin.session.handler'] = $app->share(
            function ($app) {
                $handler = new SessionHandler();

                return $handler;
            }
        );

        $app['clientlogin.records'] = $app->share(
            function ($app) {
                $records = new ClientRecords($app);

                return $records;
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
