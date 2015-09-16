<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

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
interface HandlerInterface
{
    /**
     * Login a client.
     *
     * @param string $returnpage
     *
     * @throws DisabledProviderException
     * @throws InvalidAuthorisationRequestException
     *
     * @return Response
     */
    public function login($returnpage);

    /**
     * Process a client login attempt.
     *
     * @param string $returnpage
     *
     * @return Response
     */
    public function process($returnpage);

    /**
     * Logout a client.
     *
     * @param string $returnpage
     *
     * @return Response
     */
    public function logout($returnpage);
}
