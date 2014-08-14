<?php

namespace SocialLogin;

/**
 * Twig functions
 */
class SocialLoginTwigExtensions extends \Twig_Extension
{
    /**
     * @var \Bolt\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    /**
     * @var Twig environment
     */
    private $twig = null;

    public function __construct(\Bolt\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Return the name of the extension
     *
     * @return string
     */
    public function getName()
    {
        return 'sociallogin';
    }

    /**
     * Initialise the runtime environment.
     *
     * @param \Twig_Environment $environment
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->twig = $environment;
    }

    /**
     * The functions we add to Twig
     *
     * @return array Function names and their local callbacks
     */
    public function getFunctions()
    {
        return array(
            'sociallogin' =>  new \Twig_Function_Method($this, 'getSocialLogin'),
        );
    }

    public function getSocialLogin()
    {
        $interface = new UserInterface($this->app, $this->config);
        return $interface->doDisplayLogin();
    }
}