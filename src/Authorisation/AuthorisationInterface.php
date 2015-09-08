<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Extension\Bolt\ClientLogin\Exception\DisabledProviderException;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Authentication provider interface.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
interface AuthorisationInterface
{
    /**
     * Login a client.
     *
     * @param Request          $request
     * @param SessionInterface $session
     * @param string           $returnpage
     *
     * @throws DisabledProviderException
     * @throws InvalidAuthorisationRequestException
     *
     * @return Response
     */
    public function login(Request $request, SessionInterface $session, $returnpage);

    /**
     * Process a client login attempt.
     *
     * @param Request          $request
     * @param SessionInterface $session
     * @param string           $returnpage
     *
     * @return Response
     */
    public function process(Request $request, SessionInterface $session, $returnpage);

    /**
     * Logout a client.
     *
     * @param Request          $request
     * @param SessionInterface $session
     * @param string           $returnpage
     *
     * @return Response
     */
    public function logout(Request $request, SessionInterface $session, $returnpage);
}
