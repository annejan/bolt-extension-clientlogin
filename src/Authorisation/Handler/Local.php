<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Extension\Bolt\ClientLogin\Authorisation\Manager;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use Bolt\Extension\Bolt\ClientLogin\Profile;
use Bolt\Extension\Bolt\ClientLogin\Types;
use Hautelook\Phpass\PasswordHash;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;

/**
 * Password login provider.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Local extends HandlerBase implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function login($returnpage)
    {
        if (parent::login($returnpage)) {
            // User is logged in already, from whence they came return them now.
            return new RedirectResponse($returnpage);
        }

        return $this->render();
    }

    /**
     * {@inheritdoc}
     */
    public function process($returnpage)
    {
        if (!$token = $this->getTokenManager()->getToken(Manager\Token::TOKEN_ACCESS)) {
            throw new InvalidAuthorisationRequestException('No token found for password endpoint.');
        }

        if (!$profile = $this->getRecordManager()->getProfileByProviderId('Password', $token->getResourceOwnerId())) {
            throw new InvalidAuthorisationRequestException('No matching profile record for token: ' . (string) $token);
        }

        $this->dispatchEvent(ClientLoginEvent::LOGIN_POST, $profile);

        // User is logged in already, from whence they came return them now.
        return new RedirectResponse($returnpage);
    }

    /**
     * {@inheritdoc}
     */
    public function logout($returnpage)
    {
        return parent::logout($returnpage);
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
     * @return Response
     */
    protected function render()
    {
        if ($this->request->isMethod('POST')) {
            // Validate the form data
            $form = $this->app['boltforms']
                ->getForm(Types::FORM_NAME_PASSWORD)
                ->handleRequest($this->request);

            // Validate against saved password data
            if ($form->isValid() && $this->check($form->getData())) {
                $profile = $this->getRecordManager()->getProfileByProviderId('Password', $form->getData()['username']);
                if (!$profile) {
                    throw new InvalidAuthorisationRequestException('No matching profile found');
                }

                $token = $this->getTokenManager()->getToken(Manager\Token::TOKEN_ACCESS);
                $cookie = $this->getCookieManager()->create($profile->getId(), $token);

                $response = new RedirectResponse($this->getCallbackUrl('Password'));
                $response->headers->setCookie($cookie);

                return $response;
            }
        }

        $html = $this->app['clientlogin.ui']->displayPasswordPrompt();
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
// TODO how password is stored
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
        $this->app['boltforms']->getForm(Types::FORM_NAME_PASSWORD)->addError(new FormError('Invalid user name or password.'));

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
