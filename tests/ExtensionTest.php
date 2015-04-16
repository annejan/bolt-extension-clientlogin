<?php

namespace Bolt\Extension\Bolt\ClientLogin\Tests;

use Bolt\Nut\CronRunner;
use Bolt\Nut\DatabaseRepair;
use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Tests\BoltUnitTest;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Ensure that ClientLogin loads correctly.
 *
 */
class ExtensionTest extends BoltUnitTest
{
    public function setup()
    {
        $this->resetDb();

        $app = $this->getApp();
        $command = new DatabaseRepair($app);
        $tester = new CommandTester($command);
        $tester->execute([]);
    }

    public function testExtensionRegister()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $name = $extension->getName();
        $this->assertSame($name, 'ClientLogin');
        $this->assertSame($extension, $app["extensions.$name"]);
    }

    public function testCronDaily()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register($extension);
        $name = $extension->getName();

        $app["extensions.$name"]->initialize();

        $command = new CronRunner($app);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $result = $tester->getDisplay();
        $this->assertRegExp('/ClientLogin: Clearing old sessions/', $result);
    }
}
