<?php

namespace SocialLogin;

/**
 * Social Login with OAuth via HybridAuth
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    public function getName()
    {
        return "SocialLogin";
    }

    public function initialize()
    {
        $hybridauth = new \Hybrid_Auth(array());
    }
}
