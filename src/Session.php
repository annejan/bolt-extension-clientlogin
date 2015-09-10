<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The login session.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
    /** @var RecordManager */
    private $recordManager;
    /** @var RequestStack */
    private $requestStack;
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor.
     *
     * @param RecordManager $recordManager
     */
    public function __construct(RecordManager $recordManager, RequestStack $requestStack, LoggerInterface $logger)
    {
        $this->recordManager = $recordManager;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Check if a visitor is logged in.
     *
     * @param Request $request
     *
     * @return boolean
     */
    public function isLoggedIn(Request $request = null)
    {
        if ($request === null) {
            $request = $this->requestStack->getCurrentRequest();
        }

        // No cookies is not logged in, we will reprocess
        if (!$cookie = $request->cookies->get('clientlogin_access_token')) {
            $this->setDebugMessage('Login check found no cookie.');
            return false;
        }

        $profile = $this->getRecordManager()->getProfileByAccessToken($cookie);
        if (!$profile) {
            // We shouldn't have a cookie that doesn't have a profile
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but no matching profile!', $cookie));
            return false;
        } elseif (!$profile['enabled']) {
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but profile disabled for "%s" "%s".', $cookie, $profile['provider'], $profile['resource_owner_id']));
            return false;
        } elseif ($profile['expires'] <= time()) {
            $this->setDebugMessage(sprintf('Cookie "%s" found in isLoggedIn() check, but profile has past expiry of %s (server time is %s).', $cookie, date('c', $profile['expires']), date('c', time())));
            return false;
        }

        $this->setDebugMessage(sprintf('Profile login check passed for "%s" "%s".', $cookie, $profile['provider'], $profile['resource_owner_id']));
        return true;
    }

    /**
     * Get the RecordManager DI.
     *
     * @return RecordManager
     */
    protected function getRecordManager()
    {
        return $this->recordManager;
    }

    /**
     * Write a debug message to both the debug log and the feedback array.
     *
     * @param string $message
     */
    protected function setDebugMessage($message)
    {
        $this->logger->debug($message, ['event' => 'extensions']);
    }
}
