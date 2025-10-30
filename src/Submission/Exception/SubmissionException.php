<?php

namespace DigitalInvoice\Submission\Exception;

/**
 * Base exception for all submission-related errors
 */
class SubmissionException extends \Exception
{
    protected array $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context information
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
