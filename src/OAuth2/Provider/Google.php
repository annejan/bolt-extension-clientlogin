<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\Provider;

use League\OAuth2\Client\Provider\Google as LeagueGoogle;

/**
 * Google provider extension.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Google extends LeagueGoogle
{
    /**
     * Sent to Google as the "access_type" parameter.
     *
     * @link https://developers.google.com/accounts/docs/OAuth2WebServer#offline
     *
     * @param string $accessType
     */
    public function setAccessType($accessType)
    {
        $this->accessType = $accessType;
    }

    /**
     * Sent to Google as the "hd" parameter.
     *
     * @link https://developers.google.com/accounts/docs/OAuth2Login#hd-param
     *
     * @param string $hostedDomain
     */
    public function setHostedDomain($hostedDomain)
    {
        $this->hostedDomain = $hostedDomain;
    }
}
