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
    private $userinterface;

    public function __construct(\Bolt\Application $app)
    {
        $this->userinterface = new UserInterface($app);
    }

    /**
     * Return the name of the extension
     *
     * @return string
     */
    public function getName()
    {
        return Extension::NAME . '_Twig';
    }

    /**
     * The functions we add to Twig
     *
     * @return array Function names and their local callbacks
     */
    public function getFunctions()
    {
        return array(
            'displaylogin'  =>  new \Twig_Function_Method($this, 'getDisplayLogin'),
            'displaylogout' =>  new \Twig_Function_Method($this, 'getDisplayLogout')
        );
    }

    public function getDisplayLogin($redirect = false)
    {
        return $this->userinterface->doDisplayLogin($redirect);
    }

    public function getDisplayLogout($redirect = false, $label = "Logout")
    {
        return $this->userinterface->doDisplayLogout($redirect, $label);
    }
}
