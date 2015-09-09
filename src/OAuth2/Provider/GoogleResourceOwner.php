<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\Provider;

use League\OAuth2\Client\Provider\GoogleUser as LeagueGoogleResourceOwner;

/**
 * Google ResourceOwner provider extension.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class GoogleResourceOwner extends LeagueGoogleResourceOwner
{
    use ClientLoginTrait;
}