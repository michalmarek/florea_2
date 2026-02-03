<?php declare(strict_types=1);

namespace Models\Customer;

use Core\Database;
use Nette\Database\Table\Selection;
use DateTime;

/**
 * CustomerRepository
 *
 * Handles database access for Customer entities.
 * Maps Czech database columns to English PHP properties.
 *
 * Database table: es_uzivatele
 *
 * Key mappings:
 * - login → login (unchanged)
 * - fak_email → email
 * - fak_jmeno → firstName
 * - fak_prijmeni → lastName
 * - fak_telefon → phone (normalized E.164 format)
 */
class CustomerRepository
{
    /**
     * Standard columns for Customer entity
     */
    private const COLUMNS = '
        id, 
        login, 
        password,
        shop, 
        lang, 
        fak_email, 
        fak_jmeno, 
        fak_prijmeni, 
        fak_osloveni,
        fak_telefon,
        fak_firma, 
        fak_ulice, 
        fak_mesto, 
        fak_psc, 
        fak_country,
        fak_ic, 
        fak_dic,
        aktivni, 
        schvaleny, 
        newsletter,
        datum, 
        prihlasen
    ';

    /**
     * Find customer by ID
     *
     * @param int $id Customer ID
     * @return Customer|null Customer entity or null if not found
     */
    public function findById(int $id): ?Customer
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM es_uzivatele
            WHERE id = ?
        ";

        $row = Database::query($query, $id)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find customer by login
     *
     * @param string $login Customer login
     * @return Customer|null Customer entity or null if not found
     */
    public function findByLogin(string $login): ?Customer
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM es_uzivatele
            WHERE login = ?
        ";

        $row = Database::query($query, $login)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find customer by email (fak_email OR login)
     *
     * Used for password reset - finds customer by their email address.
     * Searches in both login and fak_email to handle cases where:
     * - Customer uses original login email (login === fak_email)
     * - Customer changed fak_email but remembers it
     *
     * Safe to use because emailExistsForAnotherCustomer() prevents duplicates,
     * so this will always return max 1 customer.
     *
     * @param string $email Customer email
     * @return Customer|null Customer entity or null if not found
     */
    public function findByEmail(string $email): ?Customer
    {
        $query = "
        SELECT " . self::COLUMNS . "
        FROM es_uzivatele
        WHERE login = ? OR fak_email = ?
        LIMIT 1
    ";

        $row = Database::query($query, $email, $email)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Check if email exists for another customer
     *
     * Checks if email is used by ANY other customer (in login OR fak_email).
     * Allows customer to use same email in their own login and fak_email,
     * but prevents using email that belongs to another customer.
     *
     * Used for:
     * - Profile email change validation
     * - Registration email validation
     * - Password reset email lookup
     *
     * @param string $email Email to check
     * @param int $customerId Current customer ID to exclude from check
     * @return bool True if email is used by another customer
     */
    public function emailExistsForAnotherCustomer(string $email, int $customerId): bool
    {
        $query = "
        SELECT COUNT(*) as count
        FROM es_uzivatele
        WHERE (login = ? OR fak_email = ?)
          AND id != ?
    ";

        $row = Database::query($query, $email, $email, $customerId)->fetch();

        return $row->count > 0;
    }

    /**
     * Check if login already exists in database
     *
     * @param string $login Login to check
     * @param int|null $excludeId Customer ID to exclude from check (for profile edit)
     * @return bool True if login exists
     */
    public function loginExists(string $login, ?int $excludeId = null): bool
    {
        $query = "
            SELECT COUNT(*) as count
            FROM es_uzivatele
            WHERE login = ?
        ";

        $params = [$login];

        // Při editaci profilu vyloučíme aktuálního zákazníka
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }

        $row = Database::query($query, ...$params)->fetch();

        return $row->count > 0;
    }

    /**
     * Create new customer
     *
     * @param array $data Customer data
     * @return Customer Created customer entity
     */
    public function create(array $data): Customer
    {
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $row = Database::table('es_uzivatele')->insert([
            'login' => $data['login'],
            'password' => $passwordHash,
            'shop' => $data['shopId'],
            'lang' => $data['language'] ?? 'cs',
            'fak_email' => $data['email'],
            'fak_jmeno' => $data['firstName'],
            'fak_prijmeni' => $data['lastName'],
            'fak_osloveni' => $data['salutation'] ?? '',
            'fak_telefon' => $data['phone'] ?? '',
            'fak_firma' => $data['companyName'] ?? '',
            'fak_ulice' => $data['street'] ?? '',
            'fak_mesto' => $data['city'] ?? '',
            'fak_psc' => $data['postalCode'] ?? '',
            'fak_country' => $data['country'] ?? 'cz',
            'fak_ic' => $data['companyId'] ?? '',
            'fak_dic' => $data['vatId'] ?? '',
            'aktivni' => $data['active'] ?? '1',
            'schvaleny' => '0',  // Vždy nepotvrzený při registraci
            'newsletter' => $data['newsletter'] ?? '0',
            'datum' => date('Y-m-d H:i:s'),
        ]);

        return $this->mapToEntity($row);
    }

    /**
     * Update customer data
     *
     * @param int $id Customer ID
     * @param array $data Updated data
     * @return Customer Updated customer entity
     */
    public function update(int $id, array $data): Customer
    {
        $updateData = [];

        // Map only provided fields
        if (isset($data['email'])) {
            $updateData['fak_email'] = $data['email'];
        }
        if (isset($data['firstName'])) {
            $updateData['fak_jmeno'] = $data['firstName'];
        }
        if (isset($data['lastName'])) {
            $updateData['fak_prijmeni'] = $data['lastName'];
        }
        if (isset($data['phone'])) {
            $updateData['fak_telefon'] = $data['phone'];
        }
        if (isset($data['companyName'])) {
            $updateData['fak_firma'] = $data['companyName'];
        }

        // Update in database
        Database::table('es_uzivatele')
            ->where('id', $id)
            ->update($updateData);

        // Return updated entity
        return $this->findById($id);
    }

    /**
     * Update customer password
     *
     * @param int $id Customer ID
     * @param string $newPassword New plain text password
     * @return void
     */
    public function updatePassword(int $id, string $newPassword): void
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        Database::table('es_uzivatele')
            ->where('id', $id)
            ->update([
                'password' => $passwordHash,
            ]);
    }

    /**
     * Update last login timestamp
     *
     * @param int $id Customer ID
     * @return void
     */
    public function updateLastLogin(int $id): void
    {
        Database::table('es_uzivatele')
            ->where('id', $id)
            ->update(['prihlasen' => date('Y-m-d H:i:s')]);
    }

    /**
     * Activate pre-registered customer account
     *
     * Used when customer registers after placing order without account.
     * Sets password and activates the account.
     *
     * @param int $id Customer ID
     * @param string $password New plain text password
     * @return void
     */
    public function activate(int $id, string $password): void
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        Database::table('es_uzivatele')
            ->where('id', $id)
            ->update([
                'password' => $passwordHash,
                'aktivni' => '1',
            ]);
    }

    /**
     * Map multiple database rows to Customer entities
     *
     * @param iterable $rows Database rows from Selection
     * @return Customer[]
     */
    public function mapRowsToEntities(iterable $rows): array
    {
        $customers = [];
        foreach ($rows as $row) {
            $customers[] = $this->mapToEntity($row);
        }
        return $customers;
    }

    /**
     * Map database row to Customer entity
     *
     * Converts Czech column names to English properties:
     * - fak_email → email
     * - fak_jmeno → firstName
     * - fak_prijmeni → lastName
     * - fak_telefon → phone
     * ... etc
     *
     * @param object $row Database row from Nette Database
     * @return Customer Customer entity
     */
    private function mapToEntity(object $row): Customer
    {
        return new Customer(
            id: (int) $row->id,
            login: $row->login,
            email: $row->fak_email,
            firstName: $row->fak_jmeno,
            lastName: $row->fak_prijmeni,
            salutation: $row->fak_osloveni ?: null,
            phone: $row->fak_telefon ?: null,

            // Billing address
            companyName: $row->fak_firma ?: null,
            billingStreet: $row->fak_ulice,
            billingCity: $row->fak_mesto,
            billingPostalCode: $row->fak_psc,
            billingCountry: $row->fak_country,
            companyId: $row->fak_ic ?: null,
            vatId: $row->fak_dic ?: null,

            // Metadata
            shopId: (int) $row->shop,
            language: $row->lang,
            active: $row->aktivni === '1',
            approved: $row->schvaleny === '1',
            newsletterConsent: $row->newsletter === '1',
            registeredAt: $row->datum && $row->datum !== '0000-00-00 00:00:00'
                ? ($row->datum instanceof DateTime ? $row->datum : new DateTime($row->datum))
                : null,
            lastLoginAt: $row->prihlasen && $row->prihlasen !== '0000-00-00 00:00:00'
                ? ($row->prihlasen instanceof DateTime ? $row->prihlasen : new DateTime($row->prihlasen))
                : null,

            // Password (private)
            passwordHash: $row->password,
        );
    }
}