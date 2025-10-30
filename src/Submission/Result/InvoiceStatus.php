<?php

namespace DigitalInvoice\Submission\Result;

/**
 * Invoice status information from tax authority
 */
class InvoiceStatus
{
    /**
     * @param string $status Current status code
     * @param string|null $statusDate Date/time of status update
     * @param string|null $rejectionReason Reason for rejection (if rejected)
     * @param array $statusHistory History of status changes
     * @param array $rawResponse Complete raw response from the API
     * @param array $metadata Additional country-specific metadata
     */
    public function __construct(
        public string $status,
        public ?string $statusDate = null,
        public ?string $rejectionReason = null,
        public array $statusHistory = [],
        public array $rawResponse = [],
        public array $metadata = []
    ) {
    }

    /**
     * Check if invoice is accepted/cleared
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return in_array($this->status, ['accepted', 'cleared', 'approved', 'delivered']);
    }

    /**
     * Check if invoice was rejected
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return in_array($this->status, ['rejected', 'failed', 'error']);
    }

    /**
     * Check if invoice is still pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'queued', 'submitted']);
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'status_date' => $this->statusDate,
            'rejection_reason' => $this->rejectionReason,
            'status_history' => $this->statusHistory,
            'metadata' => $this->metadata,
        ];
    }
}
