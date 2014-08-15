<?php

namespace ClientLogin;

/**
 * Twig functions
 */
class ClientLoginTwigExtensions extends \Twig_Extension
{
    /**
     * @var UserInterface class object
     */
    private $interface;

    /**
     * @var Twig environment
     */
    private $twig = null;

    public function __construct(\Bolt\Application $app, $config)
    {
        $this->interface = new UserInterface($app, $config);
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
            'clientlogin'  =>  new \Twig_Function_Method($this, 'getClientLogin'),
            'clientlogout' =>  new \Twig_Function_Method($this, 'getClientLogout')
        );
    }

    public function getClientLogin($redirect = false)
    {
        return $this->interface->doDisplayLogin($redirect);
    }

    public function getClientLogout($redirect = false, $label = "Logout")
    {
        return $this->interface->doDisplayLogout($redirect, $label);
    }
}
