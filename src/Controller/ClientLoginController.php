<?php

namespace Bolt\Extension\Bolt\ClientLogin\Controller;

use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Symfony\Component\HttpFoundation;

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
                return new RedirectResponse($this->getRedirect($app, $request), Response::HTTP_FOUND);
            } else {
                return new Response($result['error'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            // This shouldn't happen, just die here
            return new Response('<pre>Provider not given</pre>', Response::HTTP_BAD_REQUEST);
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
        return new RedirectResponse($this->getRedirect($app, $request), Response::HTTP_FOUND);
    }

    /**
     * HybridAuth endpoint — passes all login requests to HybridAuth
     *
     * @param \Silex\Application $app
     */
    public function authenticationEndpoint(Application $app, Request $request)
    {
        \Hybrid_Endpoint::process();

        return new Response('', Response::HTTP_OK);
    }

    /**
     * Get the redirect URL
     *
     * @param Application $app
     * @param Request $request
     *
     * @return string
     */
    private function getRedirect(Application $app, Request $request)
    {
        $returnpage = $app['request']->get('redirect');

        if ($returnpage) {
            return str_replace($app['resources']->getUrl('hosturl'), '', $returnpage);
        } else {
            return $app['resources']->getUrl('hosturl');
        }
    }
}
