<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Session;
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

        $app['clientlogin.db'] = $app->share(
            function ($app) {
                $records = new Database($app);

                return $records;
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
