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
class HandlerBaseTest extends HandlerUnitTest
{

    /**
     * @expectedException \Bolt\Extension\Bolt\ClientLogin\Exception\InvalidProviderException
     * @expectedExceptionMessage Invalid provider.
     *
     * NOTE: This test expect the exception that currently is emitted from
     * HandlerBase::getProviderName()
     */
    public function testProcessWithCode()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $request = Request::create('/authenticate/endpoint', 'GET', ['code' => 't00secret']);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;

        $remote = $this->getMock(
            '\Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler\Remote',
            ['getAccessToken'],
            [$app, $app['request_stack']]
        );
        $remote
            ->expects($this->once())
            ->method('getAccessToken')
        ;
        $app['clientlogin.handler.remote'] = $remote;
        $app['clientlogin.handler.remote']->process('/gum-tree/koala');
    }

    /**
     * @ expectedException \Bolt\Extension\Bolt\ClientLogin\Exception\InvalidProviderException
     * @ expectedExceptionMessage Invalid provider.
     */
    public function testProcessWithCodeProviderInvalid()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $request = Request::create('/authenticate/endpoint', 'GET', ['code' => 't00secret', 'provider' => 'FooBar']);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;

        $remote = $this->getMock(
            '\Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler\Remote',
            ['getAccessToken'],
            [$app, $app['request_stack']]
        );
        $remote
            ->expects($this->once())
            ->method('getAccessToken')
        ;
        $app['clientlogin.handler.remote'] = $remote;
        $app['clientlogin.handler.remote']->process('/gum-tree/koala');
    }

    /**
     * @ expectedException \Bolt\Extension\Bolt\ClientLogin\Exception\InvalidProviderException
     * @ expectedExceptionMessage Invalid provider.
     */
    public function testProcessWithCodeProviderValid()
    {
//         $app = $this->getApp();
//         $extension = new Extension($app);
//         $app['extensions']->register($extension);
//         $app['extensions']->initialize();

//         $request = Request::create('/authenticate/endpoint', 'GET', ['code' => 't00secret', 'provider' => 'Generic']);
//         $requestStack = new RequestStack();
//         $requestStack->push($request);
//         $app['request'] = $request;
//         $app['request_stack'] = $requestStack;

//         $app['clientlogin.config']->set();

/*
  "enabled" => false
  "clientId" => null
  "clientSecret" => null
  "scopes" => []

 */

//         $base = new Remote($app, $app['request_stack']);
//         $response = $base->process('/gum-tree/koala');

// dump($response);
    }

//     public function testProcessX()
//     {
//         $app = $this->getApp();
//         $extension = new Extension($app);
//         $app['extensions']->register($extension);
//         $app['extensions']->initialize();

//         $request = Request::create('/authenticate/endpoint');
//         $requestStack = new RequestStack();
//         $requestStack->push($request);
//         $app['request'] = $request;
//         $app['request_stack'] = $requestStack;

//         $base = new Remote($app, $app['request_stack']);
//         $response = $base->process('/gum-tree/koala');

// dump($response);
//     }
}
