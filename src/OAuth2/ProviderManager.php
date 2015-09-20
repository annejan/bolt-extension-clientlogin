<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provider object management class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProviderManager
{
    /** @var Config */
    protected $config;
    /** @var \GuzzleHttp\Client */
    protected $guzzleClient;
    /** @var \Psr\Log\LoggerInterface */
    protected $logger;
    /** @var string */
    protected $rootUrl;
    /** @var string */
    protected $providerName;

    /**
     * Constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config, Client $guzzleClient, LoggerInterface $logger, $rootUrl)
    {
        $this->config = $config;
        $this->guzzleClient = $guzzleClient;
        $this->logger = $logger;
        $this->rootUrl = $rootUrl;
    }

    /**
     * Set the provider for this request.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @throws Exception\InvalidProviderException
     */
    public function setProvider(Application $app, Request $request)
    {
        try {
            $providerName = $this->getProviderName($request);
        } catch (Exception\InvalidProviderException $e) {
            $providerName = 'generic';
        }
        $app['clientlogin.provider'] = $app['clientlogin.provider.' . strtolower($providerName)];

        $this->setProviderHandler($app);
    }

    /**
     * Get a provider class object.
     *
     * @param string $providerName
     *
     * @throws Exception\InvalidProviderException
     *
     * @return AbstractProvider
     */
    public function getProvider($providerName)
    {
        $this->logger->debug('[ClientLogin]: Creating provider ' . $providerName);

        /** @var \League\OAuth2\Client\Provider\AbstractProvider $providerClass */
        $providerClass = '\\Bolt\\Extension\\Bolt\\ClientLogin\\OAuth2\\Provider\\' . $providerName;

        if (!class_exists($providerClass)) {
            throw new Exception\InvalidProviderException(Exception\InvalidProviderException::INVALID_PROVIDER);
        }

        $options = $this->getProviderOptions($providerName);
        $collaborators = ['httpClient' => $this->guzzleClient];

        return $this->provider = new $providerClass($options, $collaborators);
    }

    /**
     * Get a corrected provider name form a request
     *
     * @param Request $request
     *
     * @throws Exception\InvalidProviderException
     *
     * @return string
     */
    public function getProviderName(Request $request = null)
    {
        // If the provider name is set, we assume this is called post ->before()
        if ($this->providerName !== null) {
            return $this->providerName;
        }

        // If we have no provider name set, and no valid request, we're out of
        // cycle… and that's like bad… 'n stuff
        if ($request === null) {
            throw new \RuntimeException('Attempting to get provider name outside of the request cycle.');
        }

        $provider = $request->query->get('provider');
        if (empty($provider)) {
            throw new Exception\InvalidProviderException(Exception\InvalidProviderException::INVALID_PROVIDER);
        }

        return $this->providerName = ucwords(strtolower($provider));
    }

    /**
     * Get a provider config for passing to the library.
     *
     * @param string $providerName
     *
     * @throws Exception\ConfigurationException
     *
     * @return array
     */
    public function getProviderOptions($providerName)
    {
        $providerConfig = $this->config->getProvider($providerName);

        if (empty($providerConfig['clientId'])) {
            throw new Exception\ConfigurationException('Provider client ID required: ' . $providerName);
        }
        if (empty($providerConfig['clientSecret'])) {
            throw new Exception\ConfigurationException('Provider secret key required: ' . $providerName);
        }
        if (empty($providerConfig['scopes'])) {
            throw new Exception\ConfigurationException('Provider scope(s) required: ' . $providerName);
        }

        return[
            'clientId'     => $providerConfig['clientId'],
            'clientSecret' => $providerConfig['clientSecret'],
            'scope'        => $providerConfig['scopes'],
            'redirectUri'  => $this->getCallbackUrl($providerName),
        ];
    }

    /**
     * Get the Authorisation\AuthorisationInterface class to handle the request.
     *
     * @param \Silex\Application $app
     *
     * @throws InvalidAuthorisationRequestException
     */
    protected function setProviderHandler(Application $app)
    {
        $providerName = $this->getProviderName();
        if ($providerName === null) {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request was missing a provider in the GET.', ['event' => 'extensions']);
            throw new Exception\InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        $providerConfig = $app['clientlogin.config']->getProvider($providerName);
        if ($providerConfig === null) {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request provider did not match any configured providers.', ['event' => 'extensions']);
            throw new Exception\InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        if ($providerConfig['enabled'] !== true && $providerName !== 'Generic') {
            $app['logger.system']->debug('[ClientLogin][Controller]: Request provider was disabled.', ['event' => 'extensions']);
            throw new Exception\InvalidAuthorisationRequestException('Authentication configuration error. Unable to proceed!');
        }

        if ($providerName === 'Local') {
            if (!isset($app['boltforms'])) {
                throw new \RuntimeException('Local handler requires BoltForms (v2.5.0 or later preferred).');
            }
            $app['clientlogin.handler'] = clone $app['clientlogin.handler.local'];

            return;
        }

        $app['clientlogin.handler'] = clone $app['clientlogin.handler.remote'];
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
        $url = $this->rootUrl . $this->config->get('basepath') . "/endpoint?$key=$providerName";
        $this->logger->debug("[ClientLogin]: Setting callback URL: $url");

        return $url;
    }
}
