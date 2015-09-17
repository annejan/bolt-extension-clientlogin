<?php

namespace Bolt\Extension\Bolt\ClientLogin\Controller;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\Types;
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
     * Before middleware to load Guzzle 6.
     *
     * @param Request     $request
     * @param Application $app
     */
    public function before(Request $request, Application $app)
    {
        $baseDir = dirname(dirname(__DIR__));

        require $baseDir . '/lib/GuzzleHttp/Guzzle/functions_include.php';
        require $baseDir . '/lib/GuzzleHttp/Promise/functions_include.php';
        require $baseDir . '/lib/GuzzleHttp/Psr7/functions_include.php';

        $loader = new \Composer\Autoload\ClassLoader();
        $loader->setPsr4('GuzzleHttp\\', [
            $baseDir . '/lib/GuzzleHttp/Guzzle',
            $baseDir . '/lib/GuzzleHttp/Promise',
            $baseDir . '/lib/GuzzleHttp/Psr7',
        ]);
        $loader->setPsr4('GuzzleHttp\\Promise\\', [
            $baseDir . '/lib/GuzzleHttp/Promise',
        ]);
        $loader->setPsr4('GuzzleHttp\\Psr7\\', [
            $baseDir . '/lib/GuzzleHttp/Psr7',
        ]);
        $loader->register(true);

        // Debug logger
        if ($this->config->isDebug()) {
            $debuglog = $app['resources']->getPath('cache') . '/authenticate.log';
            $app['logger.system']->pushHandler(new StreamHandler($debuglog, Logger::DEBUG));
        }
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
        $response = $this->getFinalResponse($app, $request, 'logout');
        $response->headers->clearCookie(Types::TOKEN_COOKIE_NAME, $app['resources']->getUrl('root'));

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
            $authorise = $this->getAuthoriseClass($app, $request);

            $response = $authorise->{$action}($this->getRedirectUrl($app));
//             $authorise->setFeedback('message', 'Login was successful.');

//         try {
//         }
//         catch (IdentityProviderException $e) {
//             // Thrown by the OAuth2 library
//             $authorise->setFeedback('debug', $e->getMessage());
//             $authorise->setFeedback('message', 'An exception occurred authenticating with the provider.');
//             $response = new Response('Access denied!', Response::HTTP_FORBIDDEN);
//         }
//         catch (InvalidAuthorisationRequestException $e) {
//             // Thrown deliberately internally
//             $authorise->setFeedback('debug', $e->getMessage());
//             $authorise->setFeedback('message', 'An exception occurred authenticating with the provider.');
//             $response = new Response('Access denied!', Response::HTTP_FORBIDDEN);
//             $response = new RedirectResponse($this->getRedirectUrl($app));
//         }
//         catch (\Exception $e) {
//             // Yeah, this can't be good…
//             $this->setFeedback('debug', $e->getMessage());
//             $this->setFeedback('message', 'A server error occurred, we are very sorry and someone has been notified!');
//             $response = new RedirectResponse($this->getRedirectUrl($app));
//         }
        $app['twig']->addGlobal('clientlogin', $authorise->getFeedback());

        return $response;
    }

    /**
     * Get the Authorisation\AuthorisationInterface class to handle the request.
     *
     * @param \Silex\Application $app
     * @param Request            $request
     *
     * @throws InvalidAuthorisationRequestException
     *
     * @return AuthoriseInterface
     */
    private function getAuthoriseClass(Application $app, Request $request)
    {
        if (!$providerName = $this->getProviderName($app, $request)) {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request was missing a provider in the GET.', ['event' => 'extensions']);
            throw new InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        if ($app['clientlogin.config']->getProvider($providerName) === null) {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request provider did not match any configured providers.', ['event' => 'extensions']);
            throw new InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        if ($app['clientlogin.config']->getProvider($providerName)['enabled'] !== true) {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request provider was disabled.', ['event' => 'extensions']);
            throw new InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        if ($providerName === 'Password') {
            return $app['clientlogin.handler.local'];
        }

        return $app['clientlogin.handler.remote'];
    }

    /**
     * Get the provider name used.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return string
     */
    private function getProviderName(Application $app, Request $request)
    {
        if ($providerName = $request->query->get('provider')) {
            return $providerName;
        } elseif ($providerName = $request->query->get(str_replace('.', '_', $app['clientlogin.config']->get('response_noun')))) {
            return $providerName;
        }
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
