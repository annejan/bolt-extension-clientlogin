<?php

namespace Bolt\Extension\Bolt\ClientLogin\Controller;

use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Extension\Bolt\ClientLogin\Session;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Cookie;
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
        $returnpage = $this->setRedirectUrl($app, $request);

        $app['clientlogin.session']->doLogin($request, $returnpage);

        $response = $app['clientlogin.session']->getResponse();

        // If we have a good response, set a cookie
        if (!$response->isClientError()) {
            $expire = '+' . $this->config['login_expiry'] . ' days';
            $value = $app['randomgenerator']->generateString(32);
            $cookie = new Cookie('bolt_clientlogin', $value, $expire, '/', null, false, false);
            $response->headers->setCookie($cookie);

            $app['logger.system']->debug('Setting cookie: ' . $cookie);
        } else {
            $app['logger.system']->debug('Session returned a bad status code: ' . $response->getStatusCode());
        }

        return $response;
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

        $app['clientlogin.session']->logout($returnpage);

        $response = $app['clientlogin.session']->getResponse();

        if (!$response->isClientError()) {
            $app['logger.system']->debug('Clearing cookie:');
            $response->headers->clearCookie('bolt_clientlogin');
        } else {
            $app['logger.system']->debug('Session returned a bad status code: ' . $response->getStatusCode());
        }

        return $response;
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
        $code = $request->get('code');
        if (empty($code)) {
            $html = '<h1>Authentication Code Error!</h1><p>Oh... look... kitten...</p><img src="http://emergencykitten.com/img/random" />';
            return new Response($html, Response::HTTP_FORBIDDEN);
        }

        // Given state must match previously stored one to mitigate CSRF attack
        if (!$app['clientlogin.session']->checkStateToken($request->get('state'))) {
            $html = '<h1>Authentication Token Error!</h1><p>Oh... look... kitten...</p><img src="http://emergencykitten.com/img/random" />';
            return new Response($html, Response::HTTP_FORBIDDEN);
        }

        $app['clientlogin.session']->loginCheckOAuth($request, $url);

        return $app['clientlogin.session']->getResponse();
    }

    /**
     * Save the redirect URL
     *
     * @param \Silex\Application $app
     * @param Request            $request
     */
    private function setRedirectUrl(Application $app, Request $request)
    {
        $returnpage = $request->get('redirect');

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
