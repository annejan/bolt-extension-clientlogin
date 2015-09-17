<?php

namespace Bolt\Extension\Bolt\ClientLogin;

/**
 * Feedback message class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Feedback
{
    /** @var array */
    protected $feedback = [];

    public function __construct()
    {
    }

    /**
     * Static to return a class instance.
     *
     * @return Feedback
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Get the saved feedback array.
     *
     * @return array
     */
    public function getFeedback()
    {
        return $this->feedback;
    }

    /**
     * Set a feedback error of message that will be passed to Twig as a global.
     *
     * @param string $state
     * @param string $message
     *
     * @throws \InvalidArgumentException
     */
    public function setFeedback($state, $message)
    {
        if (empty($state) || !in_array($state, ['error', 'message', 'debug'])) {
            throw new \InvalidArgumentException("Feedback state can only be 'error', 'message', or 'debug'.");
        }
        $this->feedback[$state][] = $message;
    }
}
