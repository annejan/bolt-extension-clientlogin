<?php

namespace Bolt\Extension\Bolt\ClientLogin;

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
        $this->config = $config;
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
        isset($this->config[$key]) ? $this->config[$key] : null;
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
     * @param mixed $value
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
}
