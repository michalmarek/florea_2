<?php declare(strict_types=1);

namespace Core\Email;

use Core\Email\Provider\EmailProvider;
use Core\Email\Provider\MaileonProvider;
use Core\Email\Provider\NetteMailProvider;

/**
 * Email Service
 *
 * Main service for sending emails. Automatically routes emails to the
 * appropriate provider based on message type:
 * - Maileon: For customer-facing transactional emails (has maileonEventId)
 * - SMTP: For internal/admin notifications (has htmlBody)
 *
 * Usage in presenter:
 *
 * public function __construct(
 *     Container $container,
 *     private EmailService $emailService,
 * ) {
 *     parent::__construct($container);
 * }
 *
 * public function actionResetPassword(): void {
 *     $message = new EmailMessage(
 *         to: 'customer@email.cz',
 *         subject: 'Reset hesla',
 *         maileonEventId: 123,
 *         personalizedData: ['firstName' => 'Jan', ...]
 *     );
 *
 *     $this->emailService->send($message);
 * }
 */
class EmailService
{
    public function __construct(
        private MaileonProvider $maileonProvider,
        private NetteMailProvider $netteMailProvider,
        private bool $loggingEnabled = true,
        private ?string $logPath = null,
    ) {}

    /**
     * Send email via appropriate provider
     *
     * Automatically routes to:
     * - MaileonProvider if message has maileonEventId
     * - NetteMailProvider if message has htmlBody
     *
     * @param EmailMessage $message Message to send
     * @throws \Exception If sending fails
     * @return void
     */
    public function send(EmailMessage $message): void
    {
        try {
            // Route to appropriate provider based on message type
            if ($message->isMaileonEmail()) {
                $this->maileonProvider->send($message);
                $this->log($message, 'Maileon');
            } else {
                $this->netteMailProvider->send($message);
                $this->log($message, 'SMTP');
            }

        } catch (\Exception $e) {
            // Log failure
            $this->logError($message, $e);

            // Re-throw so presenter can handle it
            throw $e;
        }
    }

    /**
     * Log successful email send
     *
     * @param EmailMessage $message Message that was sent
     * @param string $provider Provider used (Maileon or SMTP)
     * @return void
     */
    private function log(EmailMessage $message, string $provider): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $logMessage = sprintf(
            "[%s] [%s] To: %s | Subject: %s | Type: %s",
            date('Y-m-d H:i:s'),
            $provider,
            $message->to,
            $message->subject,
            $message->isMaileonEmail()
                ? "Maileon Event #{$message->maileonEventId}"
                : "SMTP"
        );

        $this->writeLog($logMessage);
    }

    /**
     * Log email sending error
     *
     * @param EmailMessage $message Message that failed
     * @param \Exception $exception Exception that occurred
     * @return void
     */
    private function logError(EmailMessage $message, \Exception $exception): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $logMessage = sprintf(
            "[%s] [ERROR] To: %s | Subject: %s | Error: %s",
            date('Y-m-d H:i:s'),
            $message->to,
            $message->subject,
            $exception->getMessage()
        );

        $this->writeLog($logMessage);
    }

    /**
     * Write log message to file
     *
     * @param string $message Log message
     * @return void
     */
    private function writeLog(string $message): void
    {
        if ($this->logPath === null) {
            // Fallback to error_log if no path configured
            error_log($message);
            return;
        }

        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Append to log file
        file_put_contents(
            $this->logPath,
            $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}