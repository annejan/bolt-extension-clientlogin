<?php

namespace ClientLogin;

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
     * @var Silex\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    public function __construct(Silex\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationLogin(\Silex\Application $app, Request $request)
    {
        $auth = new Session($this->app, $this->config);

        if ($auth->isLoggedIn()) {
            // User is already logged in, return them... somewhere
            $this->doRedirect($this->app);
        } else {
            $provider = $request->query->get('provider');

            if ($provider) {
                // Attempt login
                $result = $auth->doLogin($provider);

                if ($result['result']) {
                    // Login done, redirect
                    $this->doRedirect($this->app);
                } else {
                    return $result['error'];
                }

            } else {
                // This shouldn't happen, just die here
                return '<pre>Provider not given</pre>';
            }
        }
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
     * HybridAuth endpoint — passes all login requests to HybridAuth
     *
     * @param \Silex\Application $app
     * @return multitype:
     */
    public function getAuthenticationEndpoint(\Silex\Application $app, Request $request)
    {
        \Hybrid_Endpoint::process();
    }

    /**
     * Do the best redirect we can
     *
     * @param \Silex\Application $app
     */
    private function doRedirect(\Silex\Application $app)
    {
        $returnpage = $app['request']->get('redirect');

        if($returnpage) {
            $returnpage = str_replace($app['paths']['hosturl'], '', $returnpage);
            simpleredirect($returnpage);
        } else {
            simpleredirect($app['paths']['hosturl']);
        }
    }
}
