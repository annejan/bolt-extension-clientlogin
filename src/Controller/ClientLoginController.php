<?php

namespace Bolt\Extension\Bolt\ClientLogin\Controller;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\CookieManager;
use Bolt\Extension\Bolt\ClientLogin\Response\FailureResponse;
use Bolt\Extension\Bolt\ClientLogin\Response\SuccessRedirectResponse;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ClientLogin authentication controller
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ClientLoginController implements ControllerProviderInterface
{
    const FINAL_REDIRECT_KEY = 'bolt.clientlogin.redirect';

    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    private $config;

    /**
     * @param \Silex\Application $app
     *
     * @return \Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $this->config = $app['clientlogin.config'];

        /** @var $ctr \Silex\ControllerCollection */
        $ctr = $app['controllers_factory']
            ->before([$this, 'before']);

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
     * Before middleware to:
     * - Add our logging handler during debug mode
     * - Set the request's provider in the provider manager
     *
     * @param Request     $request
     * @param Application $app
     */
    public function before(Request $request, Application $app)
    {
        if ($this->config->isDebug()) {
            $debuglog = $app['resources']->getPath('cache') . '/authenticate.log';
            $app['logger.system']->pushHandler(new StreamHandler($debuglog, Logger::DEBUG));
        }

        // Fetch the request off the stack so we don't get called out of cycle
        $request = $app['request_stack']->getCurrentRequest();
        $app['clientlogin.provider.manager']->setProvider($app, $request);
    }

    /**
     * Login route.
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return Response
     */
    public function authenticationLogin(Application $app, Request $request)
    {
        if (!$request->isSecure()) {
            // Log a warning if this route is not HTTPS
            $msg = sprintf("[ClientLogin][Controller]: Login route '%s' is not being served over HTTPS. This is insecure and vulnerable!", $request->getPathInfo());
            $app['logger.system']->critical($msg, ['event' => 'extensions']);
        }
        $this->setFinalRedirectUrl($app, $request);

        return $this->getFinalResponse($app, $request, 'login');
    }

    /**
     * Logout route.
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return RedirectResponse
     */
    public function authenticationLogout(Application $app, Request $request)
    {
        if (!$app['clientlogin.provider.manager']->getProviderName()) {
            $request->query->set('provider', 'Generic');
        }
        $response = $this->getFinalResponse($app, $request, 'logout');
        CookieManager::clearResponseCookies($response, $app['clientlogin.config']->getCookiePaths());

        return $response;
    }

    /**
     * Authorisation endpoint.
     *
     * For OAuth this will be the reply endpoint.
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return Response
     */
    public function authenticationEndpoint(Application $app, Request $request)
    {
        return $this->getFinalResponse($app, $request, 'process');
    }

    /**
     * Get the required route response.
     *
     * @param Application $app
     * @param Request     $request
     * @param string      $action
     *
     * @return Response
     */
    private function getFinalResponse(Application $app, Request $request, $action)
    {
        $response = $app['clientlogin.handler']->{$action}();
        if ($response instanceof SuccessRedirectResponse) {
            $response->setTargetUrl($this->getRedirectUrl($app));
        }

// Check that our response classes are OK
//$this->isResponseValid($response);


//         try {
//         }
//         catch (IdentityProviderException $e) {
//             // Thrown by the OAuth2 library
//             $app['clientlogin.feedback']->set('debug', $e->getMessage());
//             $app['clientlogin.feedback']->set('message', 'An exception occurred authenticating with the provider.');
//             $response = new Response('Access denied!', Response::HTTP_FORBIDDEN);
//         }
//         catch (InvalidAuthorisationRequestException $e) {
//             // Thrown deliberately internally
//             $app['clientlogin.feedback']->set('debug', $e->getMessage());
//             $app['clientlogin.feedback']->set('message', 'An exception occurred authenticating with the provider.');
//             $response = new Response('Access denied!', Response::HTTP_FORBIDDEN);
//             $response = new RedirectResponse($this->getRedirectUrl($app));
//         }
//         catch (\Exception $e) {
//             // Yeah, this can't be good…
//             $app['clientlogin.feedback']->set('debug', $e->getMessage());
//             $app['clientlogin.feedback']->set('message', 'A server error occurred, we are very sorry and someone has been notified!');
//             $response = new RedirectResponse($this->getRedirectUrl($app));
//         }

        return $response;
    }

    /**
     * For now have a fit if the responses are invalid.
     *
     * @param Response $response
     *
     * @throws \Exception
     *
     * @internal
     */
    private function isResponseValid(Response $response)
    {
        if ($response instanceof SuccessRedirectResponse) {
            return;
        }

        if ($response instanceof FailureResponse) {
            return;
        }

        throw \Exception('ClientLogin handler returned a response of type: ' . gettype($response) . ' and must be either SuccessRedirectResponse or FailureResponse');
    }

    /**
     * Save the redirect URL to the session.
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @return string
     */
    private function setFinalRedirectUrl(Application $app, Request $request)
    {
        if ($returnpage = $request->get('redirect')) {
            $returnpage = str_replace($app['resources']->getUrl('hosturl'), '', $returnpage);
        } else {
            $returnpage = $app['resources']->getUrl('hosturl');
        }

        $app['session']->set(self::FINAL_REDIRECT_KEY, $returnpage);

        return $returnpage;
    }

    /**
     * Get the saved redirect URL from the session.
     *
     * @param \Silex\Application $app
     *
     * @return string
     */
    private function getRedirectUrl($app)
    {
        if ($returnpage = $app['session']->get(self::FINAL_REDIRECT_KEY)) {
            return $returnpage;
        }

        return $app['resources']->getUrl('hosturl');
    }

    /**
     * Clear the redirect URL.
     *
     * @param \Silex\Application $app
     */
    private function clearRedirectUrl($app)
    {
        $app['session']->remove(self::FINAL_REDIRECT_KEY);
    }
}
