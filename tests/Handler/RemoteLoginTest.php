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
class RemoteLoginTest extends BoltUnitTest
{
    /**
     * @expectedException \Bolt\Extension\Bolt\ClientLogin\Exception\InvalidProviderException
     */
    public function testLoginNoProvider()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $request = Request::create('/authenticate/login');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;

        $base = new Remote($app, $app['request_stack']);
        $base->login('/gum-tree/koala');
    }

    /**
     * @expectedException \Bolt\Extension\Bolt\ClientLogin\Exception\DisabledProviderException
     */
    public function testLoginDisabledProvider()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $request = Request::create('/authenticate/login');
        $request->query->add(array('provider' => 'Google'));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;

        $base = new Remote($app, $app['request_stack']);
        $base->login('/gum-tree/koala');
    }

    /**
     *
     */
    public function testLoginAuthorisationRedirect()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $provider = $app['clientlogin.config']->getProvider('Google');
        $provider['enabled'] = true;
        $app['clientlogin.config']->set('providers', array('Google' => $provider));

        $request = Request::create('/authenticate/login');
        $request->query->add(array('provider' => 'Google'));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;

        $base = new Remote($app, $app['request_stack']);
        $response = $base->login('/gum-tree/koala');

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
        // TODO see if we can mock the initial respose
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     *
     */
    public function testLoginIsLoggedIn()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $provider = $app['clientlogin.config']->getProvider('Google');
        $provider['enabled'] = true;
        $app['clientlogin.config']->set('providers', array('Google' => $provider));

        $request = Request::create('/authenticate/login');
        $request->query->add(array('provider' => 'Google'));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $app['request'] = $request;
        $app['request_stack'] = $requestStack;
        $app['clientlogin.session'] = $this->getClientLoginSession($app, true);
        $app['session'] = $this->getLoggedInSession($app);

        $base = new Remote($app, $app['request_stack']);
        $response = $base->login('/gum-tree/koala');

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertSame('/gum-tree/koala', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());

        $this->assertTrue($response->isRedirect('/gum-tree/koala'));
    }

    public function testGuzzle6Loaded()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $app['extensions']->initialize();

        $guzzleConf = $app['clientlogin.guzzle']->getConfig();
        $this->assertRegExp('#GuzzleHttp\/6#', $guzzleConf['headers']['User-Agent']);
    }

    protected function getClientLoginSession(Application $app, $fakeIsLogged = true)
    {
        $mock = $this->getMock(
            '\Bolt\Extension\Bolt\ClientLogin\Authorisation\Session',
            array('isLoggedIn'),
            array($app['clientlogin.records'], $app['session'], $app['request_stack'], $app['logger.system'])
        );
        $mock
            ->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn($this->returnValue($fakeIsLogged))
        ;

        return $mock;
    }

    protected function getLoggedInSession(Application $app)
    {
        $accessToken = new AccessToken([
            'access_token' => '0verTher3Dad!',
            'resource_owner_id' => '2223097779'
        ]);
        $sessionToken = new SessionToken('fe4687dd-6d5b-44ae-af5e-db0e4c8b407c', $accessToken);
        $app['session']->set(Manager\Token::TOKEN_ACCESS, $sessionToken);

        return $app['session'];
    }
}
