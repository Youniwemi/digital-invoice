<?php

namespace DigitalInvoice\Submission\Result;

/**
 * Result of invoice validation
 */
class ValidationResult
{
    /**
     * @param bool $valid Whether the invoice is valid for submission
     * @param array $errors List of validation errors (blocking)
     * @param array $warnings List of warnings (non-blocking)
     * @param array $metadata Additional validation metadata
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public array $metadata = []
    ) {
    }

    /**
     * Check if there are any errors
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get all issues (errors + warnings)
     *
     * @return array
     */
    public function getAllIssues(): array
    {
        return array_merge(
            array_map(fn($e) => ['type' => 'error', 'message' => $e], $this->errors),
            array_map(fn($w) => ['type' => 'warning', 'message' => $w], $this->warnings)
        );
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }
}
