<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Extension\Bolt\ClientLogin\Database;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidProviderException;
use Bolt\Extension\Bolt\ClientLogin\Exception\DisabledProviderException;
use Bolt\Extension\Bolt\ClientLogin\Exception\ConfigurationException;
use Bolt\Extension\Bolt\ClientLogin\Exception\AccessDeniedException;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidCookieException;

/**
 * OAuth login provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class OAuth extends AuthorisationBase implements AuthorisationInterface
{
    /** @var AbstractProvider */
    protected $provider;
    /** @var string */
    protected $providerName;
    /** @var AccessToken */
    protected $accessToken;
    /** @var ResourceOwnerInterface */
    protected $resourceOwner;

    /**
     * {@inheritdoc}
     */
    public function login(Request $request, SessionInterface $session, $returnpage)
    {
        $this->setProviderName($request);

        if ($this->getConfig()->getProvider($this->providerName)['enabled'] !== true) {
            throw new DisabledProviderException();
        }

        if ($this->isLoggedIn($request)) {
            // Get the user object for the event
//$sessionToken = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS);
            // Event dispatcher
//$this->dispatchEvent('clientlogin.Login', $sessionToken['data']);

            // User is logged in already, from whence they came return them now.
            return new RedirectResponse($returnpage);
        } else {
$approvalPrompt = 'force';

            return $this->getAuthorisationRedirectResponse($approvalPrompt);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, SessionInterface $session, $returnpage)
    {
        $this->setProviderName($request);

        $resourceOwner = $this->getOauthResourceOwner($request);
// dump($providerToken);
// dump($providerToken->getToken());
// dump($providerToken->getRefreshToken());
// dump($providerToken->getExpires());

dump('finally…');
dump($this->getRefreshToken($providerToken));
die();
    }

    /**
     * {@inheritdoc}
     */
    public function logout(Request $request, SessionInterface $session, $returnpage)
    {
    }

    public function isLoggedIn(Request $request)
    {
        // No cookies is not logged in, we will reprocess
        if (!$cookie = $request->cookies->get('bolt_clientlogin_session')) {
            return false;
        }

        $profile = $this->getRecordManager()->getProfileByAccessToken($cookie);
        if (!$profile) {
            // We shouldn't have a cookie that doesn't have a profile
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but no matching profile!', $cookie));
            throw new InvalidCookieException('No matching profile found.');
        } elseif (!$profile['enabled']) {
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but profile disabled for "%s" "%s".', $cookie, $profile['provider'], $profile['esource_owner_id']));
            return false;
        } elseif ($profile['expires'] >= time()) {
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but profile has expired.', $cookie));
            return false;
        }

        return true;
    }

    protected function getOauthResourceOwner(Request $request)
    {
        if ($cookie = $request->cookies->get('bolt_clientlogin_session')) {
            $profile = $this->getRecordManager()->getProfileByAccessToken($cookie);

            if (!$profile) {
                throw new AccessDeniedException('No matching profile found.');
            } elseif (!$profile['enabled']) {
                throw new AccessDeniedException('Profile disabled.');
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
            $this->getRecordManager()->updateProfile($this->providerName, $accessToken, $resourceOwner);
        }
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
        $provider = $this->getProvider($this->providerName);

        if ($this->providerName === 'Google' && $approvalPrompt == 'force') {
            $provider->setAccessType('offline');
        }

        $options = array_merge($this->getProviderOptions($this->providerName), ['approval_prompt' => $approvalPrompt]);
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
     * @throws InvalidAuthorisationRequestException
     *
     * @return AccessToken
     */
    protected function getAccessToken(Request $request)
    {
        $code = $request->query->get('code');

        if ($code === null) {
            $this->setDebugMessage('Attempt to get an OAuth2 acess token with an empty code in the request.');

            throw new InvalidAuthorisationRequestException('No provider access code.');
        }
        $options = ['code' => $code];

        // Try to get an access token using the authorization code grant.
        return $this->getProvider()->getAccessToken('authorization_code', $options);
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
     *
     * @param string $providerName
     *
     * @throws InvalidProviderException
     *
     * @return AbstractProvider
     */
    protected function getProvider()
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        if ($this->providerName === null) {
            throw new \RuntimeException('The function getProvider() called before setProviderName()');
        }

        $this->setDebugMessage("Creating provider $this->providerName");

        /** @var \League\OAuth2\Client\Provider\AbstractProvider $providerClass */
        $providerClass = '\\Bolt\\Extension\\Bolt\\ClientLogin\\OAuth2\\Provider\\' . $this->providerName;

        if (!class_exists($providerClass)) {
            throw new InvalidProviderException(InvalidProviderException::INVALID_PROVIDER);
        }

        $options = $this->getProviderOptions($this->providerName);
        $collaborators = ['httpClient' => new \GuzzleHttp\Client()];

        return $this->provider = new $providerClass($options, $collaborators);
    }

    /**
     * Get a corrected provider name form a request
     *
     * @param Request $request
     *
     * @return string
     */
    protected function setProviderName(Request $request)
    {
        $provider = $request->query->get('provider');

        // Handle BC for old library
        if (empty($provider)) {
            $provider = $request->query->get('hauth_done');
        }

        if (empty($provider)) {
            throw new InvalidProviderException(InvalidProviderException::INVALID_PROVIDER);
        }

        return $this->providerName = ucwords(strtolower($provider));
    }

    /**
     * Get a provider config for passing to the library.
     *
     * @param string $providerName
     *
     * @return array
     */
    protected function getProviderOptions($providerName)
    {
        $providerConfig = $this->getConfig()->getProvider($providerName);

        if (empty($providerConfig['clientId'])) {
            throw new ConfigurationException('Provider client ID required: ' . $providerName);
        }
        if (empty($providerConfig['clientSecret'])) {
            throw new ConfigurationException('Provider secret key required: ' . $providerName);
        }
        if (empty($providerConfig['scopes'])) {
            throw new ConfigurationException('Provider scope(s) required: ' . $providerName);
        }

        return[
            'clientId'     => $providerConfig['clientId'],
            'clientSecret' => $providerConfig['clientSecret'],
            'scope'        => $providerConfig['scopes'],
            'redirectUri'  => $this->getCallbackUrl($providerName),
        ];
    }
}
