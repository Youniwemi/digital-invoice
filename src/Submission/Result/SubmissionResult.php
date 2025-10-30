<?php

namespace DigitalInvoice\Submission\Result;

/**
 * Result object returned after submitting an invoice
 */
class SubmissionResult
{
    /**
     * @param bool $success Whether the submission was successful
     * @param string|null $referenceId Internal reference ID for tracking (used for getStatus())
     * @param string|null $governmentId ID assigned by tax authority (UUID, IRN, Flow ID, etc.)
     * @param string $status Current status ('submitted', 'accepted', 'rejected', 'pending', etc.)
     * @param string|null $qrCode QR code for the invoice (if applicable, e.g., ZATCA)
     * @param string|null $signedXml Signed/stamped invoice XML (if applicable)
     * @param string|null $error Error message if submission failed
     * @param array $rawResponse Complete raw response from the API
     * @param array $metadata Additional country-specific metadata
     */
    public function __construct(
        public bool $success,
        public ?string $referenceId,
        public ?string $governmentId,
        public string $status,
        public ?string $qrCode = null,
        public ?string $signedXml = null,
        public ?string $error = null,
        public array $rawResponse = [],
        public array $metadata = []
    ) {
    }

    /**
     * Check if the invoice was accepted by the tax authority
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->success && in_array($this->status, ['accepted', 'cleared', 'approved']);
    }

    /**
     * Check if the invoice was rejected
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return in_array($this->status, ['rejected', 'failed', 'error']);
    }

    /**
     * Check if the invoice is pending processing
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'queued']);
    }

    /**
     * Get human-readable status message
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        if ($this->error) {
            return $this->error;
        }

        return match ($this->status) {
            'accepted', 'cleared', 'approved' => 'Invoice successfully submitted and accepted',
            'pending', 'processing' => 'Invoice is being processed',
            'rejected', 'failed' => 'Invoice was rejected',
            default => "Invoice status: {$this->status}"
        };
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'reference_id' => $this->referenceId,
            'government_id' => $this->governmentId,
            'status' => $this->status,
            'qr_code' => $this->qrCode,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
