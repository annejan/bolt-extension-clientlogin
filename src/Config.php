<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Helpers\Arr;

/**
 * Configuration provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Config
{
    /** @var array */
    private $config;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $default = $this->getDefaultConfig();
        $this->config = Arr::mergeRecursiveDistinct($default, $config);
        $this->setupProviderConfig();
    }

    /**
     * Check for a config element.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function has($key)
    {
        return isset($this->config[$key]) ? true : false;
    }

    /**
     * Get a config element.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    /**
     * Set a config element.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    /**
     * Get a button label.
     *
     * @param string $key
     *
     * @return string
     */
    public function getLabel($key)
    {
        return isset($this->config['label'][$key]) ? $this->config['label'][$key] : null;
    }

    /**
     * Get a provider config.
     *
     * @param string $key
     *
     * @return array
     */
    public function getProvider($key)
    {
        return isset($this->config['providers'][$key]) ? $this->config['providers'][$key] : null;
    }

    /**
     * Get a template name.
     *
     * @param string $key
     *
     * @return string
     */
    public function getTemplate($key)
    {
        return isset($this->config['template'][$key]) ? $this->config['template'][$key] : null;
    }

    /**
     * Get the cookie paths we set for.
     *
     * @return array
     */
    public function getCookiePaths()
    {
        return $this->config['allowed_cookie_paths'] ? (array) $this->config['allowed_cookie_paths'] : ['/'];
    }

    /**
     * Check if we're running debug mode.
     *
     * @return boolean
     */
    public function isDebug()
    {
        return (boolean) $this->config['debug']['enabled'];
    }

    /**
     * Default config options
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        $options = [
            'enabled' => false,
            'keys'    => [
                'clientId'     => null,
                'clientSecret' => null
            ],
            'scopes' => []
        ];

        return [
            'providers' => [
                'Password' => $options,
                'Google'   => $options,
                'Facebook' => $options,
                'Twitter'  => $options,
                'GitHub'   => $options,
            ],
            'allowed_cookie_paths' => null,
            'basepath' => 'authenticate',
            'template' => [
                'button'          => '_button.twig',
                'feedback'        => '_feedback.twig',
                'profile'         => '_profile.twig',
                'password'        => '_password.twig',
                'password_parent' => 'password.twig'
            ],
            'label' => array(
                'logout' => 'Logout'
            ),
            'zocial'       => false,
            'login_expiry' => 14,
            'debug'        => [
                'enabled' => false,
            ],
            'response_noun' => 'authenticate'
        ];
    }

    /**
     * Set up config and defaults
     *
     * This has evolved from HybridAuth configuration and we need to cope as such
     */
    protected function setupProviderConfig()
    {
        // Handle old provider config
        $providersConfig = [];
        foreach ($this->config['providers'] as $provider => $values) {
            // This needs to match the provider class name for OAuth
            $name = ucwords(strtolower($provider));

            // On/off switch
            $providersConfig[$name]['enabled'] = $values['enabled'];

            // Keys
            $providersConfig[$name]['clientId']     = isset($values['keys']['id']) ? $values['keys']['id'] : $values['keys']['clientId'];
            $providersConfig[$name]['clientSecret'] = isset($values['keys']['secret']) ? $values['keys']['secret'] : $values['keys']['clientSecret'];

            // Scopes
            if (isset($values['scopes'])) {
                $providersConfig[$name]['scopes'] = $values['scopes'];
            } elseif (isset($values['scope']) && !isset($values['scopes'])) {
                $providersConfig[$name]['scopes'] = explode(' ', $values['scope']);
            }
        }

        // Handle old debug parameter
        if (isset($this->config['debug_mode'])) {
            $this->config['debug']['enabled'] = (boolean) $this->config['debug_mode'];
        }

        // Write it all back
        $this->config['providers'] = $providersConfig;
    }
}
