<?php declare(strict_types=1);

namespace Core\Email\Provider;

use Core\Email\EmailMessage;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ImportReference;
use de\xqueue\maileon\api\client\transactions\ImportContactReference;
use de\xqueue\maileon\api\client\contacts\Permission;
use InvalidArgumentException;

/**
 * Maileon Provider
 *
 * Sends transactional emails via Maileon API.
 * Uses transaction-based sending with pre-configured templates in Maileon.
 *
 * Typical use cases:
 * - Password reset emails
 * - Registration confirmation
 * - Order confirmation
 *
 * Usage:
 * $provider = new MaileonProvider('api_key_here', 'https://api.maileon.com/1.0/');
 * $provider->send($emailMessage);
 */
class MaileonProvider implements EmailProvider
{
    private TransactionsService $transactionsService;

    public function __construct(
        string $apiKey,
        string $baseUrl,
    ) {
        // Initialize Maileon Transactions Service
        $config = [
            'BASE_URI' => $baseUrl,
            'API_KEY' => $apiKey,
            'DEBUG' => false,
        ];

        $this->transactionsService = new TransactionsService($config);
    }

    /**
     * Send email via Maileon
     *
     * @param EmailMessage $message Message to send
     * @throws InvalidArgumentException If message is not Maileon-compatible
     * @throws \Exception If Maileon API fails
     * @return void
     */
    public function send(EmailMessage $message): void
    {
        // Validate: This is Maileon email (has maileonEventId)
        if (!$message->isMaileonEmail()) {
            throw new InvalidArgumentException(
                'MaileonProvider requires maileonEventId. This looks like an SMTP email.'
            );
        }

        try {
            // Create transaction
            $transaction = new Transaction();

            // Set recipient
            $transaction->import = new ImportReference();
            $transaction->import->contact = new ImportContactReference();
            $transaction->import->contact->email = $message->to;
            $transaction->import->contact->permission = Permission::$OTHER->getCode();

            // Set transaction type (Event ID)
            $transaction->type = $message->maileonEventId;

            // Set personalized data as content
            $transaction->content = $message->personalizedData;

            // Send transaction (array of transactions)
            $transactions = [$transaction];
            $response = $this->transactionsService->createTransactions(
                $transactions,
                true,   // doIPCheck - validate email
                false   // ignoreInvalidTransactions
            );

            // Check if successful
            if (!$response->isSuccess()) {
                throw new \Exception(
                    "Maileon API returned error (Status: " . $response->getStatusCode() . "): "
                    . ($response->getBodyData() ?: 'Unknown error')
                );
            }

        } catch (\Exception $e) {
            // Re-throw with context
            throw new \Exception(
                sprintf(
                    "Failed to send email via Maileon to %s (transaction type: %d): %s",
                    $message->to,
                    $message->maileonEventId,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }
}