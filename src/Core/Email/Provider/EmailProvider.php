<?php declare(strict_types=1);

namespace Core\Email\Provider;

use Core\Email\EmailMessage;

/**
 * Email Provider Interface
 *
 * Contract for all email providers (Maileon, SMTP, SendGrid, etc.)
 * Each provider must implement the send() method.
 *
 * This allows EmailService to work with any provider without knowing
 * the implementation details.
 */
interface EmailProvider
{
    /**
     * Send an email message
     *
     * @param EmailMessage $message Message to send
     * @throws \Exception If sending fails
     * @return void
     */
    public function send(EmailMessage $message): void;
}