<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Cookie;

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
    public function login(Request $request, SessionInterface $session, $returnpage)
    {
        $provider = $this->getConfig()->getProvider($this->getProviderName());
        if ($provider['enabled'] !== true) {
            throw new Exception\DisabledProviderException();
        }

        if ($this->app['clientlogin.session']->isLoggedIn($request)) {
            // Get the user object for the event
//$sessionToken = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS);
            // Event dispatcher
//$this->dispatchEvent('clientlogin.Login', $sessionToken['data']);

            // User is logged in already, from whence they came return them now.
            return new RedirectResponse($returnpage);
        } else {
            return $this->getAuthorisationRedirectResponse();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, SessionInterface $session, $returnpage)
    {
        $accessToken = $this->getAccessToken($request);
        $resourceOwner = $this->getProvider()->getResourceOwner($accessToken);

        $profile = $this->getRecordManager()->getProfileByResourceOwnerId($this->getProviderName(), $resourceOwner->getId());
        if ($profile === false) {
            $this->setDebugMessage(sprintf('No profile found for %s ID %s', $this->getProviderName(), $resourceOwner->getId()));
            $write = $this->getRecordManager()->writeProfile('insert', $this->getProviderName(), $accessToken, $resourceOwner);
        } else {
            $this->setDebugMessage(sprintf('Profile found for %s ID %s', $this->getProviderName(), $resourceOwner->getId()));
            $write = $this->getRecordManager()->writeProfile($profile['guid'], $this->getProviderName(), $accessToken, $resourceOwner);
        }

        if (!$write) {
            throw new \Exception('why no rite?');
        }

        // Update the session record
        $profile = $this->getRecordManager()->getProfileByResourceOwnerId($this->getProviderName(), $resourceOwner->getId());
        $this->getRecordManager()->writeSession($profile['guid'], $this->getProviderName(), $accessToken);

        $response = new RedirectResponse($returnpage);
        $response->headers->setCookie($this->getCookieManager()->create($resourceOwner->getId(), $accessToken));

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(Request $request, SessionInterface $session, $returnpage)
    {
    }

/*
    protected function getOauthResourceOwner(Request $request)
    {
        if ($cookie = $request->cookies->get('clientlogin_access_token')) {
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
    }
*/

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
     * Get an access token from the OAuth provider.
     *
     * @param Request $request
     *
     * @throws IdentityProviderException
     * @throws Exception\InvalidAuthorisationRequestException
     *
     * @return AccessToken
     */
    protected function getAccessToken(Request $request)
    {
        $code = $request->query->get('code');

        if ($code === null) {
            $this->setDebugMessage('Attempt to get an OAuth2 acess token with an empty code in the request.');

            throw new Exception\InvalidAuthorisationRequestException('No provider access code.');
        }
        $options = ['code' => $code];

        // Try to get an access token using the authorization code grant.
        $accessToken = $this->getProvider()->getAccessToken('authorization_code', $options);
        $this->app['logger.system']->debug('[ClientLogin] OAuth token received', $accessToken->jsonSerialize());

        return $accessToken;
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
