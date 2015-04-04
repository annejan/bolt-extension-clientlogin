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
    /** @var string */
    const TOKENNAME = 'bolt_clientlogin_redirect';

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

        /** @var $ctr \Silex\ControllerCollection */
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
     * @return Response
     */
    public function authenticationLogin(Application $app, Request $request)
    {
        $this->setRedirectUrl($app);

        return $app['clientlogin.session']->doLogin($request);
    }

    /**
     * Logout controller
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return RedirectResponse
     */
    public function authenticationLogout(Application $app, Request $request)
    {
        $this->clearRedirectUrl($app);

        $app['clientlogin.session']->doLogout();

        // Logout done, redirect
        return new RedirectResponse($app['clientlogin.session']->getRedirectUrl(), Response::HTTP_FOUND);
    }

    /**
     * OAuth endpoint
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return Response
     */
    public function authenticationEndpoint(Application $app, Request $request)
    {
        $url = $this->getRedirectUrl($app);
        $this->clearRedirectUrl($app);

        // Check 'code' isn't empty
        if (empty($app['request']->get('code'))) {
            return new Response('Kittens!', Response::HTTP_FORBIDDEN);
        }

        // Given state must match previously stored one to mitigate CSRF attack
        if (!$app['clientlogin.session']->checkStateToken($request->get('state'))) {
            $app['clientlogin.session']->clearStateToken();

            return new Response('Kittens!', Response::HTTP_FORBIDDEN);
        }

        return $app['clientlogin.session']->doCheckLoginOAuth($request, $url);
    }

    /**
     * Save the redirect URL
     *
     * @param \Silex\Application $app
     */
    private function setRedirectUrl(Application $app)
    {
        $returnpage = $app['request']->get('redirect');

        if ($returnpage) {
            $returnpage = str_replace($app['resources']->getUrl('hosturl'), '', $returnpage);
        } else {
            $returnpage = $this->app['resources']->getUrl('hosturl');
        }

        $app['session']->set(self::TOKENNAME, $returnpage);
    }

    /**
     * Get the redirect URL
     *
     * @param \Silex\Application $app
     *
     * @return string
     */
    private function getRedirectUrl($app)
    {
        return $app['session']->get(self::TOKENNAME);
    }

    /**
     * Clear the redirect URL
     *
     * @param \Silex\Application $app
     */
    private function clearRedirectUrl($app)
    {
        $app['session']->remove(self::TOKENNAME);
    }
}
