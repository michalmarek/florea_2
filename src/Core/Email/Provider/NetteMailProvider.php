<?php declare(strict_types=1);

namespace Core\Email\Provider;

use Core\Email\EmailMessage;
use Nette\Mail\Message;
use Nette\Mail\Mailer;
use InvalidArgumentException;

/**
 * Nette Mail Provider (SMTP)
 *
 * Sends emails via SMTP using Nette Mail library.
 * Typically used for internal notifications and admin emails.
 *
 * In development: Sends to MailHog (localhost:1025)
 * In production: Sends to configured SMTP server
 *
 * Usage:
 * $provider = new NetteMailProvider($mailer, 'obchod@florea.cz', 'Florea.cz');
 * $provider->send($emailMessage);
 */
class NetteMailProvider implements EmailProvider
{
    public function __construct(
        private Mailer $mailer,              // Nette SMTP mailer
        private string $defaultFromEmail,    // Default sender email
        private string $defaultFromName,     // Default sender name
    ) {}

    /**
     * Send email via SMTP
     *
     * @param EmailMessage $message Message to send
     * @throws InvalidArgumentException If message is not SMTP-compatible
     * @throws \Exception If sending fails
     * @return void
     */
    public function send(EmailMessage $message): void
    {
        // Validate: This is SMTP email (has htmlBody)
        if (!$message->isSmtpEmail()) {
            throw new InvalidArgumentException(
                'NetteMailProvider requires htmlBody. This looks like a Maileon email.'
            );
        }

        // Create Nette Mail Message
        $mail = new Message();

        // Set sender (use custom or default)
        $mail->setFrom(
            $message->fromEmail ?? $this->defaultFromEmail,
            $message->fromName ?? $this->defaultFromName
        );

        // Set recipient
        $mail->addTo($message->to);

        // Set subject
        $mail->setSubject($message->subject);

        // Set HTML body
        if ($message->htmlBody !== null) {
            $mail->setHtmlBody($message->htmlBody, null);
        }

        // Set plain text body (optional)
        if ($message->textBody !== null) {
            $mail->setBody($message->textBody);
        }

        // Send via SMTP
        try {
            $this->mailer->send($mail);
        } catch (\Exception $e) {
            // Re-throw with context
            throw new \Exception(
                "Failed to send email via SMTP to {$message->to}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}