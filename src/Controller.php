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

    public function __construct(Silex\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;
    }

    /**
     * Logout controller
     *
     * @param \Silex\Application $app
     * @param Request $request
     * @return multitype:
     */
    public function getAuthenticationLogin(\Silex\Application $app, Request $request)
    {
        $session = $this->app['extensions.' . Extension::NAME]->session;

        if ($session->doCheckLogin()) {
            // User is already logged in, return them... somewhere
            $this->doRedirect($this->app);
        } else {
            $provider = $request->query->get('provider');

            if ($provider) {
                // Attempt login
                $result = $session->doLoginOAuth($provider);

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
     * Logout controller
     *
     * @param \Silex\Application $app
     * @param Request $request
     */
    public function getAuthenticationLogout(\Silex\Application $app, Request $request)
    {
        $session = $this->app['extensions.' . Extension::NAME]->session;

        $session->doLogout();

        // Logout done, redirect
        $this->doRedirect($this->app);
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
