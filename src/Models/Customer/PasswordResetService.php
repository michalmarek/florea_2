<?php declare(strict_types=1);

namespace Models\Customer;

use Core\Config;
use Core\Database;
use Shop\ShopContext;
use Core\Email\EmailService;
use DateTime;

/**
 * PasswordResetService
 *
 * Handles password reset business logic:
 * - Request reset (validate email, rate limiting, create token, send email)
 * - Validate token
 * - Reset password (validate token, update password, mark token as used)
 *
 * Security features:
 * - Rate limiting (max N attempts per time window)
 * - Single-use tokens (marked as used after password change)
 * - Token expiration (configured via config)
 * - Remember tokens deletion (force logout on all devices after password change)
 */
class PasswordResetService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private EmailService $emailService,
        private ShopContext $shopContext,
    ) {}

    /**
     * Request password reset for email
     *
     * Flow:
     * 1. Find customer by email
     * 2. Check rate limiting
     * 3. Generate token with expiration
     * 4. Send reset email
     *
     * @param string $email Customer email
     * @throws \Exception If email doesn't exist or rate limit exceeded
     * @return void
     */
    public function requestReset(string $email): void
    {
        // 1. Find customer by email
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            throw new \Exception('Email neexistuje v systému');
        }

        // 2. Rate limiting check
        $attempts = $this->tokenRepository->countRecentAttempts($customer->id);
        $maxAttempts = Config::get('customer.password_reset.rate_limit.max_attempts', 3);

        if ($attempts >= $maxAttempts) {
            $windowMinutes = Config::get('customer.password_reset.rate_limit.window_minutes', 15);
            throw new \Exception("Příliš mnoho pokusů. Zkuste to znovu za {$windowMinutes} minut.");
        }

        // 3. Create token with expiration
        $expiration = Config::get('customer.password_reset.token_expiration', 3600);
        $expiresAt = new DateTime('+' . $expiration . ' seconds');

        $plainToken = $this->tokenRepository->createToken($customer->id, $expiresAt);

        // 4. Send reset email
        $this->sendResetEmail($customer, $plainToken);
    }

    /**
     * Validate reset token
     *
     * Checks:
     * - Token hash exists
     * - Not expired
     * - Not used
     *
     * @param string $token Plain token from URL
     * @return array|null Token data or null if invalid
     */
    public function validateToken(string $token): ?array
    {
        return $this->tokenRepository->findValidToken($token);
    }

    /**
     * Reset password using token
     *
     * Flow:
     * 1. Validate token
     * 2. Update customer password
     * 3. Mark token as used
     * 4. Delete all remember tokens (force logout on all devices)
     *
     * @param string $token Plain token from URL
     * @param string $newPassword New plain password
     * @throws \Exception If token is invalid
     * @return int Customer ID (for auto-login or redirect)
     */
    public function resetPassword(string $token, string $newPassword): int
    {
        // 1. Validate token
        $tokenData = $this->validateToken($token);

        if (!$tokenData) {
            throw new \Exception('Neplatný nebo expirovaný token');
        }

        $customerId = $tokenData['customer_id'];

        // 2. Update password
        $this->customerRepository->updatePassword($customerId, $newPassword);

        // 3. Mark token as used
        $this->tokenRepository->markAsUsed($tokenData['id']);

        // 4. Delete all remember tokens (security - force logout on all devices)
        $this->deleteAllRememberTokens($customerId);

        return $customerId;
    }

    /**
     * Send password reset email via Maileon
     *
     * @param Customer $customer Customer to send email to
     * @param string $plainToken Plain token for reset URL
     * @return void
     * @throws \Exception
     */
    private function sendResetEmail(Customer $customer, string $plainToken): void
    {
        // Build reset link with shop domain
        $shopUrl = $this->shopContext->getUrl();
        $resetLink = "{$shopUrl}/auth/reset-password?token={$plainToken}";

        // Maileon API format - personalized data as array of key-value objects
        $personalizedData = [
            'email' => $customer->email,
            'reset_url' => $resetLink,
            'shopID' => (int) $customer->shopId,
            'lang' => 'cs',
        ];

        // Create Maileon email message
        $message = new \Core\Email\EmailMessage(
            to: $customer->email,
            subject: 'Reset hesla',
            maileonEventId: 477,
            personalizedData: $personalizedData
        );

        // Send via EmailService (automatically routes to Maileon)
        $this->emailService->send($message);
    }

    /**
     * Delete all remember tokens for customer
     *
     * Called after password change to force logout on all devices.
     * Security measure - if password was compromised, this logs out
     * the attacker from all sessions.
     *
     * @param int $customerId Customer ID
     * @return void
     */
    private function deleteAllRememberTokens(int $customerId): void
    {
        Database::table('es_uzivatele_rememberTokens')
            ->where('customer_id', $customerId)
            ->delete();
    }
}