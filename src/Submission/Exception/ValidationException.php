<?php

namespace DigitalInvoice\Submission\Exception;

/**
 * Exception thrown when invoice validation fails
 */
class ValidationException extends SubmissionException
{
    protected array $validationErrors = [];

    public function __construct(
        string $message = "",
        array $validationErrors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, ['validation_errors' => $validationErrors]);
        $this->validationErrors = $validationErrors;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
