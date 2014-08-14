<?php

namespace SocialLogin;

use Silex;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Authentication controller
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Controller
{
    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationLogin(\Silex\Application $app, Request $request)
    {
        return array();
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationLogout(\Silex\Application $app, Request $request)
    {
        return array();
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationEndpoint(\Silex\Application $app, Request $request)
    {
        return array();
    }
}
