<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth login provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Remote extends HandlerBase implements HandlerInterface
{
    /** @var AccessToken */
    protected $accessToken;
    /** @var ResourceOwnerInterface */
    protected $resourceOwner;

    /**
     * {@inheritdoc}
     */
    public function login($returnpage)
    {
        if (parent::login($returnpage)) {
            // User is logged in already, from whence they came return them now.
            return new RedirectResponse($returnpage);
        }
        return $this->getAuthorisationRedirectResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function process($returnpage)
    {
        return parent::process($returnpage);
    }

    /**
     * {@inheritdoc}
     */
    public function logout($returnpage)
    {
        return parent::logout($returnpage);
    }

    protected function getOauthResourceOwner(Request $request)
    {
        /*
        if ($cookie = $request->cookies->get(Types::TOKEN_COOKIE_NAME)) {
            $profile = $this->getRecordManager()->getProfileByAccessToken($cookie);

            if (!$profile) {
                throw new Exception\AccessDeniedException('No matching profile found.');
            } elseif (!$profile['enabled']) {
                throw new Exception\AccessDeniedException('Profile disabled.');
            }

            // Compile the options from the database record.
            $options = [
                'resource_owner_id' => $profile['resource_owner_id'],
                'refresh_token'     => $profile['refresh_token'],
                'access_token'      => $profile['access_token'],
                'expires'           => $profile['expires'],
            ];

            // Create and refresh the token
            $accessToken = $this->getRefreshToken(new AccessToken($options));
            $resourceOwner = $this->getProvider()->getResourceOwner($accessToken);

            // Save the new token data
            $this->getRecordManager()->updateProfile($this->getProviderName(), $accessToken, $resourceOwner);
        }
*/
    }

    /**
     * Create a redirect response to fetch an authorisation code.
     *
     * @param string $approvalPrompt
     *
     * @return RedirectResponse
     */
    protected function getAuthorisationRedirectResponse($approvalPrompt = 'auto')
    {
        $provider = $this->getProvider();

        if ($this->getProviderName() === 'Google' && $approvalPrompt == 'force') {
            $provider->setAccessType('offline');
        }

        $options = array_merge($this->getProviderOptions($this->getProviderName()), ['approval_prompt' => $approvalPrompt]);
        $authorizationUrl = $provider->getAuthorizationUrl($options);

        // Get the state generated and store it to the session.
        $this->getTokenManager()->setStateToken($provider->getState());
        $this->setDebugMessage('Storing state token: ' . $provider->getState());

        return new RedirectResponse($authorizationUrl);
    }

    /**
     * Get a refresh token from the OAuth provider.
     *
     * @param AccessToken $accessToken
     *
     * @throws IdentityProviderException
     *
     * @return AccessToken
     */
    protected function getRefreshToken(AccessToken $accessToken)
    {
        if ($accessToken->hasExpired()) {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->getProvider()->getAccessToken('refresh_token', ['refresh_token' => $accessToken->getRefreshToken()]);
        }

        return $accessToken;
    }

    /**
     * Get a provider config for passing to the library.
     *
     * @param string $providerName
     *
     * @throws Exception\ConfigurationException
     *
     * @return array
     */
    protected function getProviderOptions($providerName)
    {
        $providerConfig = $this->getConfig()->getProvider($providerName);

        if (empty($providerConfig['clientId'])) {
            throw new Exception\ConfigurationException('Provider client ID required: ' . $providerName);
        }
        if (empty($providerConfig['clientSecret'])) {
            throw new Exception\ConfigurationException('Provider secret key required: ' . $providerName);
        }
        if (empty($providerConfig['scopes'])) {
            throw new Exception\ConfigurationException('Provider scope(s) required: ' . $providerName);
        }

        return[
            'clientId'     => $providerConfig['clientId'],
            'clientSecret' => $providerConfig['clientSecret'],
            'scope'        => $providerConfig['scopes'],
            'redirectUri'  => $this->getCallbackUrl($providerName),
        ];
    }
}
