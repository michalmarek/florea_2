<?php declare(strict_types=1);

namespace Core\Email;

use InvalidArgumentException;

/**
 * Email Message DTO
 *
 * Represents an email to be sent through either Maileon or SMTP.
 * Routing is determined by presence of maileonEventId:
 * - If maileonEventId is set → Maileon provider
 * - If htmlBody is set → SMTP provider
 *
 * Usage:
 *
 * // Maileon email (customer-facing)
 * $message = new EmailMessage(
 *     to: 'customer@example.com',
 *     subject: 'Password Reset',
 *     maileonEventId: 123,
 *     personalizedData: [
 *         'firstName' => 'Jan',
 *         'resetToken' => 'abc123',
 *         'resetLink' => 'https://florea.cz/reset?token=abc123'
 *     ]
 * );
 *
 * // SMTP email (internal)
 * $message = new EmailMessage(
 *     to: 'admin@florea.cz',
 *     subject: 'New Order Notification',
 *     htmlBody: '<p>Order #12345 was created</p>'
 * );
 */
class EmailMessage
{
    public function __construct(
        // Required for all emails
        public readonly string $to,
        public readonly string $subject,

        // For Maileon emails (transactional events)
        public readonly ?int $maileonEventId = null,
        public readonly array $personalizedData = [],

        // For SMTP emails (direct send)
        public readonly ?string $htmlBody = null,
        public readonly ?string $textBody = null,

        // Optional sender override
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
    ) {
        // Validation: Must have either Maileon event ID OR HTML body
        if ($this->maileonEventId === null && $this->htmlBody === null) {
            throw new InvalidArgumentException(
                'EmailMessage must have either maileonEventId (for Maileon) or htmlBody (for SMTP)'
            );
        }

        // Validation: Cannot have both
        if ($this->maileonEventId !== null && $this->htmlBody !== null) {
            throw new InvalidArgumentException(
                'EmailMessage cannot have both maileonEventId and htmlBody. Choose one provider.'
            );
        }

        // Validate email address
        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$this->to}");
        }
    }

    /**
     * Check if this is a Maileon email
     *
     * @return bool True if should be sent via Maileon
     */
    public function isMaileonEmail(): bool
    {
        return $this->maileonEventId !== null;
    }

    /**
     * Check if this is an SMTP email
     *
     * @return bool True if should be sent via SMTP
     */
    public function isSmtpEmail(): bool
    {
        return $this->htmlBody !== null;
    }
}