<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\Provider;

use League\OAuth2\Client\Provider\GithubResourceOwner as LeagueGitHubResourceOwner;

/**
 * GitHub ResourceOwner provider extension.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class GitHubResourceOwner extends LeagueGitHubResourceOwner
{
    use ClientLoginTrait;
}
