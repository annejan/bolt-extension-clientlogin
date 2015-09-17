<?php

namespace Bolt\Extension\Bolt\ClientLogin\Tests;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler\Remote;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\Manager;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\Session;
use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Tests\BoltUnitTest;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\SessionToken;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Remote authentication handler class tests
 */
class RemoteLogoutTest extends BoltUnitTest
{
    public function testLogout()
    {
//         $app = $this->getApp();
//         $extension = new Extension($app);
//         $app['extensions']->register($extension);
//         $app['extensions']->initialize();

//         $request = Request::create('/authenticate/login');
//         $requestStack = new RequestStack();
//         $requestStack->push($request);
//         $app['request'] = $request;
//         $app['request_stack'] = $requestStack;

//         $base = new Remote($app, $app['request_stack']);
//         $base->login('/gum-tree/koala');
    }
}
