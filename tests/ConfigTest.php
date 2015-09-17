<?php

namespace Bolt\Extension\Bolt\ClientLogin\Tests;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Nut\CronRunner;
use Bolt\Nut\DatabaseRepair;
use Bolt\Tests\BoltUnitTest;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Config class tests
 */
class ConfigTest extends BoltUnitTest
{
    public function testDefaultConfig()
    {
        $config = new Config(array());

        $this->assertTrue($config->has('providers'), "The key 'providers' doesn't exist");
        $this->assertFalse($config->has('koalas'));

        $config->set('koalas', 'gum leaves');
        $this->assertSame('gum leaves', $config->get('koalas'));
    }

    public function testGetLabel()
    {
        $config = new Config(array());

        $this->assertSame('Logout', $config->getLabel('logout'));
    }

    public function testGetProvider()
    {
        $providers = array('Password', 'Facebook', 'Google', 'Github', 'Generic');
        $config = new Config(array());

        foreach ($providers as $provider) {
            $provider = $config->getProvider($provider);

            $this->assertArrayHasKey('enabled', $provider);
            $this->assertArrayHasKey('clientId', $provider);
            $this->assertArrayHasKey('clientSecret', $provider);
            $this->assertArrayHasKey('scopes', $provider);
            $this->assertFalse($provider['enabled']);
        }
    }

    public function testGetTemplate()
    {
        $config = new Config(array());

        $this->assertSame('_profile.twig', $config->getTemplate('profile'));
        $this->assertSame('_button.twig', $config->getTemplate('button'));
        $this->assertSame('_feedback.twig', $config->getTemplate('feedback'));
        $this->assertSame('_password.twig', $config->getTemplate('password'));
        $this->assertSame('password.twig', $config->getTemplate('password_parent'));
    }

    public function testIsDebug()
    {
        $config = new Config(array());

        $this->assertFalse($config->isDebug());
    }
}
