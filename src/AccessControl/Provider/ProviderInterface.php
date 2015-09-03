<?php

namespace Bolt\Extension\Bolt\ClientLogin\AccessControl\Provider;

/**
 * Authentication provider interface.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
interface ProviderInterface
{
    public function login();

    public function logout();
}
