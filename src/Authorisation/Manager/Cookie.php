<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Manager;

use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie manager class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Cookie
{
    /**
     * Create an authentication cookie.
     *
     * @param string      $path
     * @param AccessToken $accessToken
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function create($path, AccessToken $accessToken)
    {
        if (!$expire = $accessToken->getExpires()) {
            $expire = time() + 3600;
        }

        return new Cookie(Types::TOKEN_COOKIE_NAME, $accessToken->getToken(), $expire, $path);
    }
}
