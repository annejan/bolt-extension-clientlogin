<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Application;

/**
 * Twig functions
 */
class ClientLoginTwigExtensions extends \Twig_Extension
{
    /** @var UserInterface class object */
    private $userinterface;

    public function __construct(Application $app)
    {
        $this->app = $app;
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
            'hasauth'       => new \Twig_Function_Method($this, 'getHasAuth'),
            'displayauth'   => new \Twig_Function_Method($this, 'getDisplayAuth'),
            'displaylogin'  => new \Twig_Function_Method($this, 'getDisplayLogin'),
            'displaylogout' => new \Twig_Function_Method($this, 'getDisplayLogout')
        );
    }

    public function getHasAuth()
    {
        if ($this->app['clientlogin.session']->doCheckLogin()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayAuth($redirect = false)
    {
        return $this->userinterface->doDisplayAuth($redirect);
    }

    /**
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayLogin($redirect = false)
    {
        return $this->userinterface->doDisplayLogin($redirect);
    }

    /**
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayLogout($redirect = false)
    {
        return $this->userinterface->doDisplayLogout($redirect, $label);
    }
}
