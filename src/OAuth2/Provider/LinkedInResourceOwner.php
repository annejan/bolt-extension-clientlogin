<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\Provider;

use League\OAuth2\Client\Provider\LinkedInResourceOwner as LeagueLinkedInResourceOwner;

/**
 * LinkedIn ResourceOwner provider extension.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class LinkedInResourceOwner extends LeagueLinkedInResourceOwner
{
    use ClientLoginTrait;
}
