<?php

namespace Bolt\Extension\Bolt\ClientLogin\Controller;

use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentication controller
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ClientLoginController implements ControllerProviderInterface
{
    /** @var array Extension config */
    private $config;

    /**
     * @param \Silex\Application $app
     *
     * @return \Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $this->config = $app[Extension::CONTAINER]->config;

        /**
         * @var $ctr \Silex\ControllerCollection
         */
        $ctr = $app['controllers_factory'];

        // Member login
        $ctr->match('/login', array($this, 'authenticationLogin'))
            ->bind('authenticationLogin')
            ->method('GET');

        // Member logout
        $ctr->match('/logout', array($this, 'authenticationLogout'))
            ->bind('authenticationLogout')
            ->method('GET');

        // OAuth callback URI
        $ctr->match('/endpoint', array($this, 'authenticationEndpoint'))
            ->bind('authenticationEndpoint')
            ->method('GET|POST');

        return $ctr;
    }

    /**
     * Logout controller
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return multitype:
     */
    public function authenticationLogin(Application $app, Request $request)
    {
        $provider = $request->query->get('provider');

        if ($provider) {
            if ($provider == 'Password' && $this->config['auth']['password']['enabled']) {
                // Attempt password login
                $result = $app['clientlogin.session']->doLoginPassword();
            } elseif ($this->config['auth']['hybridauth']['providers'][$provider]['enabled']) {
                // Attempt oauth login
                $result = $app['clientlogin.session']->doLoginOAuth($provider);
            } else {
                $result = array('result' => false, 'error' => '<pre>Error: Invalid or disabled provider</pre>');
            }

            if ($result['result']) {
                // Login done, redirect
                return $this->doRedirect($app);
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
    public function authenticationLogout(Application $app, Request $request)
    {
        $app['clientlogin.session']->doLogout();

        // Logout done, redirect
        return $this->doRedirect($app);
    }

    /**
     * HybridAuth endpoint — passes all login requests to HybridAuth
     *
     * @param \Silex\Application $app
     */
    public function authenticationEndpoint(Application $app, Request $request)
    {
        \Hybrid_Endpoint::process();
    }

    /**
     * Do the best redirect we can
     *
     * @param \Silex\Application $app
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function doRedirect(Application $app)
    {
        $returnpage = $app['request']->get('redirect');

        if ($returnpage) {
            $returnpage = str_replace($app['resources']->getUrl('hosturl'), '', $returnpage);
        } else {
            $returnpage = $app['resources']->getUrl('hosturl');
        }

        return new RedirectResponse($returnpage, Response::HTTP_FOUND);
    }
}
