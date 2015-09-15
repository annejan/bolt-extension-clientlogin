<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\CookieManager;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\TokenManager;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Authorisation control class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class HandlerBase
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
    public function __construct(Application $app, RequestStack $requestStack)
    {
        if (!$request = $requestStack->getCurrentRequest()) {
            throw new Exception\ConfigurationException(sprintf('%s can not be instated outside of the request cycle.'));
        }

        $this->app    = $app;
        $this->config = $app['clientlogin.config'];
        $this->tm     = new TokenManager($app['session'], $app['randomgenerator'], $app['logger.system']);
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
     * Get the token manager instance.
     *
     * @return CookieManager
     */
    protected function getCookieManager()
    {
        return new CookieManager($this->getRecordManager(), $this->app['randomgenerator'], $this->app['resources']);
    }

    /**
     * Get the RecordManager DI.
     *
     * @return RecordManager
     */
    protected function getRecordManager()
    {
        return $this->app['clientlogin.records'];
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
     *
     * @param string $providerName
     *
     * @throws Exception\InvalidProviderException
     *
     * @return AbstractProvider
     */
    protected function getProvider()
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        if ($this->providerName === null) {
            throw new \RuntimeException('The function getProvider() called before setProviderName()');
        }

        $this->setDebugMessage("Creating provider $this->providerName");

        /** @var \League\OAuth2\Client\Provider\AbstractProvider $providerClass */
        $providerClass = '\\Bolt\\Extension\\Bolt\\ClientLogin\\OAuth2\\Provider\\' . $this->providerName;

        if (!class_exists($providerClass)) {
            throw new Exception\InvalidProviderException(Exception\InvalidProviderException::INVALID_PROVIDER);
        }

        $options = $this->getProviderOptions($this->providerName);
        $collaborators = ['httpClient' => new \GuzzleHttp\Client()];

        return $this->provider = new $providerClass($options, $collaborators);
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
        $this->app['logger.system']->debug($message, ['event' => 'extensions']);
        $this->setFeedback('debug', $message);
    }

    /**
     * Dispatch event to any listeners.
     *
     * @param string  $type    Either 'clientlogin.Login' or 'clientlogin.Logout'
     * @param Profile $profile
     */
    protected function dispatchEvent($type, Profile $profile)
    {
        if ($this->app['dispatcher']->hasListeners($type)) {
            $event = new ClientLoginEvent($profile, $this->app['clientlogin.records']->getTableName('profile'));

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
