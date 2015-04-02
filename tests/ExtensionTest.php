<?php

namespace Bolt\Extension\Bolt\ClientLogin\Tests;

use Bolt\Tests\BoltUnitTest;
use Bolt\Extension\Bolt\ClientLogin\Extension;

/**
 * Ensure that ClientLogin loads correctly.
 *
 */
class ExtensionTest extends BoltUnitTest
{
    public function testExtensionRegister()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register( $extension );
        $name = $extension->getName();
        $this->assertSame($name, 'ClientLogin');
        $this->assertSame($extension, $app["extensions.$name"]);
    }
}
