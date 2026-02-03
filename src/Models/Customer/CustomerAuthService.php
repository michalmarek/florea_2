<?php declare(strict_types=1);

namespace Models\Customer;

use Core\Database;
use Shop\ShopContext;

/**
 * CustomerAuthService
 *
 * Handles customer authentication and session management.
 * Provides login, logout, registration, and current user retrieval.
 */
class CustomerAuthService
{
    /**
     * Session key for customer data
     */
    private const SESSION_KEY = 'customer';

    public function __construct(
        private CustomerRepository $customerRepository,
        private ShopContext $shopContext,
    ) {}

    /**
     * Login customer with login and password
     *
     * @param string $login Customer login
     * @param string $password Plain text password
     * @param bool $rememberMe Create Remember Me token (default false)
     * @return Customer|null Customer entity if login successful, null otherwise
     */
    public function login(string $login, string $password, bool $rememberMe = false): ?Customer
    {
        // Find customer by login
        $customer = $this->customerRepository->findByLogin($login);

        if (!$customer) {
            return null; // Login doesn't exist
        }

        // Check if customer is active
        if (!$customer->active) {
            return null; // Account is inactive
        }

        // Verify password
        if (!$customer->verifyPassword($password)) {
            return null; // Wrong password
        }

        // Update last login timestamp
        $this->customerRepository->updateLastLogin($customer->id);

        // Store in session
        $this->storeInSession($customer);

        // Create Remember Me token if requested
        if ($rememberMe) {
            $this->createRememberToken($customer);
        }

        // Regenerate session ID (security - prevent session fixation)
        session_regenerate_id(true);

        return $customer;
    }

    /**
     * Store customer data in session
     *
     * @param Customer $customer Customer to store
     * @return void
     */
    private function storeInSession(Customer $customer): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'id' => $customer->id,
            'firstName' => $customer->firstName,
            'lastName' => $customer->lastName,
        ];
    }

    /**
     * Logout current customer
     *
     * Removes customer data from session, deletes all remember tokens,
     * and regenerates session ID.
     *
     * @return void
     */
    public function logout(): void
    {
        // Get customer ID before clearing session
        $customerId = $_SESSION[self::SESSION_KEY]['id'] ?? null;

        // Clear session
        unset($_SESSION[self::SESSION_KEY]);

        // Delete all remember tokens for this customer
        if ($customerId) {
            $this->deleteAllRememberTokens($customerId);
        }

        // Regenerate session ID (security)
        session_regenerate_id(true);
    }

    /**
     * Create Remember Me token for customer
     *
     * Generates a secure random token, stores its hash in database,
     * and sets a cookie with the plain token.
     *
     * @param Customer $customer Customer to remember
     * @param int $days Number of days to remember (default 30)
     * @return void
     */
    private function createRememberToken(Customer $customer, int $days = 30): void
    {
        // Generate random token (32 bytes = 64 hex characters)
        $token = bin2hex(random_bytes(32));

        // Hash token for storage (SHA-256)
        $tokenHash = hash('sha256', $token);

        // Calculate expiration date
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        // Store in database
        Database::table('es_uzivatele_rememberTokens')->insert([
            'customer_id' => $customer->id,
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        // Set cookie (plain token, httpOnly, secure, sameSite)
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        $result = setcookie(
            'remember_token',           // Cookie name
            $token,                      // Plain token (not hashed)
            time() + ($days * 24 * 3600), // Expiration timestamp
            '/',                         // Path
            '',                          // Domain (empty = current domain)
            $isHttps,
            true                         // HttpOnly (no JavaScript access)
        );
    }

    /**e
     * Verify Remember Me token from cookie and auto-login customer
     *
     * Checks if cookie exists, validates token against database,
     * and automatically logs in the customer if valid.
     *
     * @return Customer|null Customer if token valid, null otherwise
     */
    public function verifyRememberToken(): ?Customer
    {
        // Check if cookie exists
        if (!isset($_COOKIE['remember_token'])) {
            return null;
        }

        $plainToken = $_COOKIE['remember_token'];
        $tokenHash = hash('sha256', $plainToken);

        // Find token in database
        $tokenRow = Database::table('es_uzivatele_rememberTokens')
            ->where('token', $tokenHash)
            ->where('expires_at > NOW()')
            ->fetch();

        if (!$tokenRow) {
            // Token not found or expired - delete cookie
            $this->deleteRememberCookie();
            return null;
        }

        // Load customer
        $customer = $this->customerRepository->findById($tokenRow->customer_id);

        if (!$customer || !$customer->active) {
            // Customer not found or inactive - delete token
            $this->deleteRememberToken($tokenRow->id);
            return null;
        }

        // Auto-login: store in session
        $this->storeInSession($customer);

        // Update last login
        $this->customerRepository->updateLastLogin($customer->id);

        return $customer;
    }

    /**
     * Delete Remember Me cookie
     *
     * @return void
     */
    private function deleteRememberCookie(): void
    {
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            unset($_COOKIE['remember_token']);
        }
    }

    /**
     * Delete Remember Me token from database
     *
     * @param int $tokenId Token ID to delete
     * @return void
     */
    private function deleteRememberToken(int $tokenId): void
    {
        Database::table('es_uzivatele_rememberTokens')
            ->where('id', $tokenId)
            ->delete();

        $this->deleteRememberCookie();
    }

    /**
     * Delete all Remember Me tokens for customer
     *
     * Used on logout or password change (security measure).
     *
     * @param int $customerId Customer ID
     * @return void
     */
    private function deleteAllRememberTokens(int $customerId): void
    {
        Database::table('es_uzivatele_rememberTokens')
            ->where('customer_id', $customerId)
            ->delete();

        $this->deleteRememberCookie();
    }

    /**
     * Register new customer
     *
     * @param array $data Customer data
     * @return Customer Created customer entity
     * @throws \Exception If email or login already exists
     */
    public function register(array $data): Customer
    {
        // Check if login already exists
        if ($this->customerRepository->loginExists($data['login'])) {
            throw new \Exception('Login již existuje');
        }

        // Check if email is already taken (in login OR fak_email of any customer)
        if ($this->customerRepository->findByEmail($data['email'])) {
            throw new \Exception('Email již existuje');
        }

        // Add current shop ID
        $data['shopId'] = $this->shopContext->getId();

        // Create customer
        $customer = $this->customerRepository->create($data);

        // Auto-login after registration
        $this->storeInSession($customer);

        return $customer;
    }

    /**
     * Get currently logged in customer
     *
     * Returns fresh customer data from database.
     * Returns null if not logged in.
     *
     * @return Customer|null Customer entity or null if not logged in
     */
    public function getCurrentCustomer(): ?Customer
    {
        if (!isset($_SESSION[self::SESSION_KEY]['id'])) {
            return null;
        }

        $customerId = (int) $_SESSION[self::SESSION_KEY]['id'];

        // Fetch fresh data from database
        return $this->customerRepository->findById($customerId);
    }

    /**
     * Create view model for templates (session data + lazy loading entity)
     *
     * @return CustomerViewModel
     */
    public function createViewModel(): CustomerViewModel
    {
        if (!$this->isLoggedIn()) {
            return new CustomerViewModel(
                isLoggedIn: false,
                firstName: null,
                lastName: null,
                authService: null
            );
        }

        return new CustomerViewModel(
            isLoggedIn: true,
            firstName: $_SESSION[self::SESSION_KEY]['firstName'],
            lastName: $_SESSION[self::SESSION_KEY]['lastName'],
            authService: $this  // Pro lazy loading
        );
    }

    /**
     * Check if customer is logged in
     *
     * Fast check without database query - only checks session.
     *
     * @return bool True if logged in, false otherwise
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }
}