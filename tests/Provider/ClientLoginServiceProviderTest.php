<?php

namespace Bolt\Extension\Bolt\ClientLogin\Provider\Tests;

use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Extension\Bolt\ClientLogin\Provider\ClientLoginServiceProvider;
use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Bolt\Tests\BoltUnitTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session as SessionHandler;

/**
 * ClientLogin Service Provider tests
 */
class ClientLoginServiceProviderTest extends BoltUnitTest
{
    public function testProvider()
    {
        $app = $this->getApp();
        $provider = new ClientLoginServiceProvider($app);
        $app->register($provider);
        $extension = new Extension($app);
        $app['extensions']->register($extension);

        $this->assertNotEmpty($app['clientlogin.session']);
//         $this->assertNotEmpty($app['session']);
        $this->assertNotEmpty($app['clientlogin.db']);

        $this->assertInstanceOf('Bolt\Extension\Bolt\ClientLogin\Session', $app['clientlogin.session']);
//         $this->assertInstanceOf('Symfony\Component\HttpFoundation\Session\Session', $app['session']);
        $this->assertInstanceOf('Bolt\Extension\Bolt\ClientLogin\Database', $app['clientlogin.db']);

        $app->boot();
    }
}