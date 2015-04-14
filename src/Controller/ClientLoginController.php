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
        $ctr->match('/login', [$this, 'authenticationLogin'])
            ->bind('authenticationLogin')
            ->method('GET|POST');

        // Member logout
        $ctr->match('/logout', [$this, 'authenticationLogout'])
            ->bind('authenticationLogout')
            ->method('GET');

        // OAuth callback URI
        $ctr->match('/endpoint', [$this, 'authenticationEndpoint'])
            ->bind('authenticationEndpoint')
            ->method('GET|POST');

        return $ctr;
    }

    /**
     * Login controller
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return Response
     */
    public function authenticationLogin(Application $app, Request $request)
    {
        $returnpage = $this->setRedirectUrl($app);

        return $app['clientlogin.session']->doLogin($request, $returnpage);
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
        $returnpage = $this->getRedirectUrl($app);
        $this->clearRedirectUrl($app);

        return $app['clientlogin.session']->logout($returnpage);
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
        $code = $app['request']->get('code');
        if (empty($code)) {
            $html = '<h1>Authentication Code Error!</h1><p>Oh... look... kitten...</p><img src="http://emergencykitten.com/img/random" />';
            return new Response($html, Response::HTTP_FORBIDDEN);
        }

        // Given state must match previously stored one to mitigate CSRF attack
        if (!$app['clientlogin.session']->checkStateToken($request->get('state'))) {
            $html = '<h1>Authentication Token Error!</h1><p>Oh... look... kitten...</p><img src="http://emergencykitten.com/img/random" />';
            return new Response($html, Response::HTTP_FORBIDDEN);
        }

        return $app['clientlogin.session']->loginCheckOAuth($request, $url);
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
            $returnpage = $app['resources']->getUrl('hosturl');
        }

        $app['session']->set(self::TOKENNAME, $returnpage);

        return $returnpage;
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
        $returnpage = $app['session']->get(self::TOKENNAME);

        if ($returnpage) {
            return $returnpage;
        }

        return $app['resources']->getUrl('hosturl');
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
