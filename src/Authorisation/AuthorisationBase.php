<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Client;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Bolt\Extension\Bolt\ClientLogin\Database\Records;

/**
 * Authorisation control class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class AuthorisationBase
{
    /** @var \Bolt\Application */
    protected $app;

    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    private $config;
    /** @var \Symfony\Component\HttpFoundation\Response */
    private $response;
    /** @var TokenManager */
    private $tm;
    /** @var array */
    private $feedback = [];

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app    = $app;
        $this->config = $app['clientlogin.config'];
        $this->tm     = new TokenManager($app['session'], $app['randomgenerator'], $app['logger.system']);
    }

    /**
     * Get the token manager instance.
     *
     * @return TokenManager
     */
    protected function getTokenManager()
    {
        return $this->tm;
    }

    /**
     * Check if a visitor is logged in by session token.
     *
     * If session token doesn't exist we assume the user is not logged in.
     *
     * If session token has expired we also return a false.
     *
     * @param Request $request
     *
     * @return boolean|array
     */
    protected function isLoggedIn(Request $request)
    {
        $token = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS);
        if ($token === null) {
            $this->setDebugMessage('No token for ' .  TokenManager::TOKEN_ACCESS);

            return false;
        }

        return $token;
    }

    protected function updateLogin()
    {
        //
    }

    /**
     * Get the config DI.
     *
     * @return Config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the Records DI.
     *
     * @return Records
     */
    protected function getRecords()
    {
        return $this->app['clientlogin.records'];
    }

    /**
     * Construct the authorisation URL with query parameters.
     *
     * @param string $providerName
     *
     * @return string
     */
    protected function getCallbackUrl($providerName)
    {
        $key = $this->config->get('response_noun');
        $url = $this->app['resources']->getUrl('rooturl') . $this->getConfig()->get('basepath') . "/endpoint?$key=$providerName";
        $this->setDebugMessage("Setting callback URL: $url");

        return $url;
    }

    /**
     * Get the saved feedback array.
     *
     * @return array
     */
    public function getFeedback()
    {
        return $this->feedback;
    }

    /**
     * Set a feedback error of message that will be passed to Twig as a global.
     *
     * @param string $state
     * @param string $message
     *
     * @throws \InvalidArgumentException
     */
    public function setFeedback($state, $message)
    {
        if (empty($state) || !in_array($state, ['error', 'message', 'debug'])) {
            throw new \InvalidArgumentException("Feedback state can only be 'error', 'message', or 'debug'.");
        }
        $this->feedback[$state][] = $message;
    }

    /**
     * Write a debug message to both the debug log and the feedback array.
     *
     * @param string $message
     */
    protected function setDebugMessage($message)
    {
        $this->app['logger.system']->debug($message);
        $this->setFeedback('debug', $message);
    }

    /**
     * Dispatch event to any listeners.
     *
     * @param string $type Either 'clientlogin.Login' or 'clientlogin.Logout'
     * @param Client $user
     */
    protected function dispatchEvent($type, Client $user)
    {
        if ($this->app['dispatcher']->hasListeners($type)) {
            $tablename = $this->app['clientlogin.db']->getTableNameProfiles();
            $event     = new ClientLoginEvent($user, $tablename);

            try {
                $this->app['dispatcher']->dispatch($type, $event);
            } catch (\Exception $e) {
                if ($this->config->get('debug_mode')) {
                    dump($e);
                }

                $this->app['logger.system']->critical('ClientLogin event dispatcher had an error', ['event' => 'exception', 'exception' => $e]);
            }
        }
    }
}
