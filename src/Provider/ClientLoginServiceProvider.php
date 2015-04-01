<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider;

use Bolt\Extension\Bolt\ClientLogin\ClientRecords;
use Bolt\Extension\Bolt\ClientLogin\Extension;
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
