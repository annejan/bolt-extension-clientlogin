<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Library as Lib;
use Silex;
use Symfony\Component\HttpFoundation\Request;

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
        $this->config = $this->app[Extension::CONTAINER]->config;
    }

    /**
     * Logout controller
     *
     * @param  \Silex\Application $app
     * @param  Request            $request
     * @return multitype:
     */
    public function getAuthenticationLogin(\Silex\Application $app, Request $request)
    {
        $session = $this->app[Extension::CONTAINER]->session;

        $provider = $request->query->get('provider');

        if ($provider) {
            if ($provider == 'Password' && $this->config['auth']['password']['enabled']) {
                // Attempt password login
                $result = $session->doLoginPassword();
            } elseif ($this->config['auth']['hybridauth']['providers'][$provider]['enabled']) {
                // Attempt oauth login
                $result = $session->doLoginOAuth($provider);
            } else {
                $result = array('result' => false, 'error' => '<pre>Error: Invalid or disabled provider</pre>');
            }

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

    /**
     * Logout controller
     *
     * @param \Silex\Application $app
     * @param Request            $request
     */
    public function getAuthenticationLogout(\Silex\Application $app, Request $request)
    {
        $session = $this->app[Extension::CONTAINER]->session;

        $session->doLogout();

        // Logout done, redirect
        $this->doRedirect($this->app);
    }

    /**
     * HybridAuth endpoint — passes all login requests to HybridAuth
     *
     * @param  \Silex\Application $app
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

        if ($returnpage) {
            $returnpage = str_replace($app['paths']['hosturl'], '', $returnpage);
            Lib::simpleredirect($returnpage);
        } else {
            Lib::simpleredirect($app['paths']['hosturl']);
        }
    }
}
