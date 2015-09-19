<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2;

use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

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
