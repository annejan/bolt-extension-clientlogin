<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use Bolt\Extension\Bolt\ClientLogin\Exception\DisabledProviderException;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Bolt\Extension\Bolt\ClientLogin\FormFields;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use Hautelook\Phpass\PasswordHash;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\TokenManager;

/**
 * Password login provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Local extends HandlerBase implements HandlerInterface
{
    const FORM_NAME = 'clientlogin_password';

    /**
     * {@inheritdoc}
     */
    public function login($returnpage)
    {
        $provider = $this->getConfig()->getProvider('Password');
        if ($provider['enabled'] !== true) {
            throw new DisabledProviderException();
        }

        if ($token = $this->isLoggedIn($request)) {
            $profile = $this->getRecordManager()->getProfileByProviderId('Password', $token->getResourceOwnerId());

            if (!$profile) {
                throw new InvalidAuthorisationRequestException('No matching profile record for token: ' . (string) $token);
            }

            $this->dispatchEvent('clientlogin.Login', $profile);

            // User is logged in already, from whence they came return them now.
            return new RedirectResponse($returnpage);

        }

        return $this->render($request);
    }

    /**
     * {@inheritdoc}
     */
    public function process($returnpage)
    {
        if (!$token = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS)) {
            throw new InvalidAuthorisationRequestException('No token found for password endpoint.');
        }

        if (!$profile = $this->getRecordManager()->getProfileByProviderId('Password', $token->getResourceOwnerId())) {
            throw new InvalidAuthorisationRequestException('No matching profile record for token: ' . (string) $token);
        }

        $this->dispatchEvent('clientlogin.Login', $profile);

        // User is logged in already, from whence they came return them now.
        return new RedirectResponse($returnpage);
    }

    /**
     * {@inheritdoc}
     */
    public function logout($returnpage)
    {
        if ($token = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS)) {
            $this->getRecordManager()->deleteSession($sessionId);
        }

        $this->getTokenManager()->removeToken(TokenManager::TOKEN_ACCESS);

        return new RedirectResponse($returnpage);
    }

    /**
     * Create a profile for the provider.
     *
     * @param string $userName
     * @param string $password
     *
     * @return integer|null
     */
    public function createProfile($userName, $password)
    {
        $profile = Profile::createPasswordAuth($userName, $this->getHasher()->HashPassword($password));
        $token = $this->getTokenManager()->generateAuthToken('Password', $userName, null, null, null, null);

        return $this->getRecordManager()->insertProfile('Password', $userName, $userName, $profile, $token);
    }

    /**
     * Render a password login page.
     *
     * @param Request $request
     *
     * @return Response
     */
    protected function render(Request $request)
    {
        $formFields = FormFields::Password();
        $this->app['boltforms']->makeForm(self::FORM_NAME, 'form', [], []);
        $this->app['boltforms']->addFieldArray(self::FORM_NAME, $formFields['fields']);
        $message = '';

        if ($request->isMethod('POST')) {
            // Validate the form data
            $form = $this->app['boltforms']
                ->getForm(self::FORM_NAME)
                ->handleRequest($request);

            // Validate against saved password data
            if ($form->isValid() && $this->check($form->getData())) {
                $profile = $this->getRecordManager()->getProfileByProviderId('Password', $form->getData()['username']);
                if (!$profile) {
                    throw new InvalidAuthorisationRequestException('No matching profile found');
                }

                $token = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS);
                $cookie = $this->getCookieManager()->create($profile->getId(), $token);

                $response = new RedirectResponse($this->getCallbackUrl('Password'));
                $response->headers->setCookie($cookie);

                return $response;
            }
        }

        $fields = $this->app['boltforms']->getForm(self::FORM_NAME)->all();
        $context = [
            'parent'  => $this->getConfig()->getTemplate('password_parent'),
            'fields'  => $fields,
            'message' => $message
        ];

        // Render the Twig_Markup
        $html = $this->app['boltforms']->renderForm(self::FORM_NAME, $this->getConfig()->getTemplate('password'), $context);

        return new Response($html, Response::HTTP_OK);
    }

    /**
     * Check the password, login data, and set tokens.
     *
     * @param array $formData
     *
     * @throws InvalidAuthorisationRequestException
     *
     * @return boolean
     */
    protected function check($formData)
    {
        if (empty($formData['username']) || empty($formData['password'])) {
            throw new InvalidAuthorisationRequestException('Empty username or password data provided for password login request.');
        }

        // Look up a user profile
        $profile = $this->getRecordManager()->getProfileByProviderId('Password', $formData['username']);

        // If the profile doesn't exist, then we just want to warn of an invalid combination
        if ($profile === false) {
            return $this->getInvaildPassword($formData);
        }

        // Check the stored hash versus the POSTed one.
        if ($this->getHasher()->CheckPassword($formData['password'], $profile->getPassword())) {
            // Calculate the expiry time
            $expires = strtotime('+' . $this->getConfig()->get('login_expiry') . ' days');
            // Set the auth token into the session
            $token = $this->getTokenManager()->generateAuthToken('Password', $formData['username'], null, null, $expires, null);
            $this->getTokenManager()->setAuthToken($token);

            return true;
        }

        return $this->getInvaildPassword($formData);
    }

    /**
     * Handle the password logging, etc.
     *
     * @param array $formData
     *
     * @return boolean
     */
    protected function getInvaildPassword($formData)
    {
        $this->setDebugMessage(sprintf('No user profile record found for %s', $formData['username']));
        $this->app['boltforms']->getForm(self::FORM_NAME)->addError(new FormError('Invalid user name or password.'));

        return false;
    }

    /**
     * Get an instance of the password hasher.
     *
     * @return PasswordHash
     */
    private function getHasher()
    {
        return new PasswordHash(12, true);
    }
}
