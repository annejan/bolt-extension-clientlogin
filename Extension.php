<?php
// Membership extension for Bolt

namespace SocialLogin;

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
