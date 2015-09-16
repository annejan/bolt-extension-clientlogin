<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

/**
 * Authorisation type constants.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Types
{
    /**
     * We are the Singleton. You will be assimilated. Resistance is futile.
     */
    private function __construct()
    {
    }

    const AUTH_PASSWORD = 'password';
    const AUTH_OAUTH1 = 'oauth1';
    const AUTH_OAUTH2 = 'oauth2';

    const TOKEN_COOKIE_NAME = 'clientlogin_access_token';
    const TOKEN_SESSION_NAME = 'clientlogin_session_token';
}
