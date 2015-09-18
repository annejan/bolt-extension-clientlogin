<?php

namespace Bolt\Extension\Bolt\ClientLogin\Twig;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Extension;
use Bolt\Extension\Bolt\ClientLogin\Twig\Helper\UserInterface;

/**
 * Twig functions
 */
class ClientLoginExtension extends \Twig_Extension
{
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Extension::NAME . '_Twig';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            'hasauth'       => new \Twig_Function_Method($this, 'getHasAuth'),
            'profile'       => new \Twig_Function_Method($this, 'getWhoAmI'),
            'displayauth'   => new \Twig_Function_Method($this, 'getDisplayAuth'),
            'displaylogin'  => new \Twig_Function_Method($this, 'getDisplayLogin'),
            'displaylogout' => new \Twig_Function_Method($this, 'getDisplayLogout')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getGlobals()
    {
        return ['clientlogin' => ['feedback' => $this->app['clientlogin.feedback']]];
    }

    /**
     * Check login status
     *
     * @return boolean
     */
    public function getHasAuth()
    {
        if ($this->app['clientlogin.session']->isLoggedIn()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get profile if user is logged in
     * If the userr is not logged in just return empty array values
     *
     * @return array
     */
    public function getWhoAmI()
    {
        $visitor = array(
            'id'       => null,
            'username' => null,
            'email'    => null,
            'provider' => null
        );
        $profile = $this->app['clientlogin.session']->isLoggedIn();
        if ($profile) {
            //dump($profile);
            $visitor['id'] = $profile->id;
            $visitor['provider'] = $profile->provider;
            // do some testing for sensible defaults
            if ($profile->name) {
                $visitor['username'] = $profile->name;
            } elseif ($profile->firstName && $profile->lastName) {
                $visitor['username'] = $profile->firstName . ' ' . $profile->lastName;
            } elseif ($profile->lastName) {
                $visitor['username'] = $profile->lastName;
            } elseif ($profile->nickname) {
                $visitor['username'] = $profile->nickname;
            } else {
                $visitor['username'] = "user ". $profile->id;
            }
            if (!empty($profile->email)) {
                $visitor['email'] = $profile->email;
            }
            return $visitor;
        } else {
            return $visitor;
        }
    }

    /**
     * Display login/logout depending on status
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayAuth($redirect = false)
    {
        return $this->getUserInterface()->displayAuth($redirect);
    }

    /**
     * Display login
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayLogin($redirect = false)
    {
        return $this->getUserInterface()->displayLogin($redirect);
    }

    /**
     * Display logout
     *
     * @param string $redirect
     *
     * @return \Twig_Markup
     */
    public function getDisplayLogout($redirect = false)
    {
        return $this->getUserInterface()->displayLogout($redirect);
    }

    /**
     * Return a UserInterface object and ensure our Twig global extists.
     *
     * @return UserInterface
     */
    protected function getUserInterface()
    {
        return $this->app['clientlogin.ui'];
    }
}
