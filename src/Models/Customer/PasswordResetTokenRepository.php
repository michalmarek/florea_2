<?php declare(strict_types=1);

namespace Models\Customer;

use Core\Database;
use Core\Config;
use DateTime;

/**
 * PasswordResetTokenRepository
 *
 * Handles database operations for password reset tokens.
 * Tokens are stored in es_uzivatele_resetTokens table.
 *
 * Key features:
 * - Token hash storage (SHA-256)
 * - Expiration validation
 * - Single-use tokens (used_at flag)
 * - Rate limiting support
 * - Automatic garbage collection
 */
class PasswordResetTokenRepository
{
    /**
     * Create new password reset token
     *
     * Generates random token (32 bytes = 64 hex chars),
     * stores its SHA-256 hash in database,
     * and returns the plain token for email.
     *
     * @param int $customerId Customer ID
     * @param DateTime $expiresAt Expiration datetime
     * @return string Plain token (to send via email, NOT the hash)
     */
    public function createToken(int $customerId, DateTime $expiresAt): string
    {
        // Generate random token
        $plainToken = bin2hex(random_bytes(32));

        // Hash for storage
        $tokenHash = hash('sha256', $plainToken);

        // Insert into database
        Database::table('es_uzivatele_resetTokens')->insert([
            'customer_id' => $customerId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Return plain token (for email)
        return $plainToken;
    }

    /**
     * Find valid token by plain token string
     *
     * Validates:
     * - Token hash exists in database
     * - Not expired (expires_at > NOW)
     * - Not used (used_at IS NULL)
     *
     * Also performs garbage collection (deletes old expired tokens).
     *
     * @param string $token Plain token from URL
     * @return array|null Token data [id, customer_id, token_hash, expires_at, created_at, used_at] or null if invalid
     */
    public function findValidToken(string $token): ?array
    {
        // Garbage collection - delete old expired tokens
        $this->deleteExpiredTokens();

        // Hash the plain token to compare with stored hash
        $tokenHash = hash('sha256', $token);

        // Find token with validation
        $row = Database::table('es_uzivatele_resetTokens')
            ->where('token_hash', $tokenHash)
            ->where('expires_at > NOW()')  // Not expired
            ->where('used_at', null)        // Not used
            ->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'customer_id' => (int) $row->customer_id,
            'token_hash' => $row->token_hash,
            'expires_at' => $row->expires_at,
            'created_at' => $row->created_at,
            'used_at' => $row->used_at,
        ];
    }

    /**
     * Mark token as used
     *
     * Sets used_at timestamp to prevent token reuse.
     * Called after successful password reset.
     *
     * @param int $tokenId Token ID
     * @return void
     */
    public function markAsUsed(int $tokenId): void
    {
        Database::table('es_uzivatele_resetTokens')
            ->where('id', $tokenId)
            ->update(['used_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Count recent reset attempts for customer (rate limiting)
     *
     * Returns number of tokens created in the last X minutes
     * (configured via customer.password_reset.rate_limit.window_minutes).
     *
     * Used to prevent abuse - max N attempts per time window.
     *
     * @param int $customerId Customer ID
     * @return int Number of attempts in configured time window
     */
    public function countRecentAttempts(int $customerId): int
    {
        $windowMinutes = Config::get('customer.password_reset.rate_limit.window_minutes', 15);

        return Database::table('es_uzivatele_resetTokens')
            ->where('customer_id', $customerId)
            ->where("created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)", $windowMinutes)
            ->count();
    }

    /**
     * Delete expired tokens (garbage collection)
     *
     * Deletes tokens where expires_at is older than 24 hours.
     * Called automatically during token validation to keep table clean.
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpiredTokens(): int
    {
        return Database::table('es_uzivatele_resetTokens')
            ->where('expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')
            ->delete();
    }
}