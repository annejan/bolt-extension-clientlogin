<?php

namespace SocialLogin;

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
    public function getAuthenticationRoot(\Silex\Application $app = null)
    {
        return array();
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationLogin(\Silex\Application $app = null)
    {
        return array();
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationLogout(\Silex\Application $app = null)
    {
        return array();
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationEndpoint(\Silex\Application $app = null)
    {
        return array();
    }
}
