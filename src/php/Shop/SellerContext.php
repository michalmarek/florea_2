<?php declare(strict_types=1);

namespace Core\Shop;

class SellerContext
{
    public readonly int $id;

    private readonly array $data;

    private function __construct(int $id, array $data)
    {
        $this->id = $id;
        $this->data = $data;
    }

    // === Basic info ===

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getPhoneNumber(): string
    {
        return $this->data['phoneNumber'];
    }

    public function getEmail(): string
    {
        return $this->data['email'];
    }

    public function getNotificationEmail(): string
    {
        return $this->data['notificationEmail'];
    }

    // === Company details ===

    public function getCompanyName(): string
    {
        return $this->data['companyName'];
    }

    public function getStreet(): string
    {
        return $this->data['street'];
    }

    public function getCity(): string
    {
        return $this->data['city'];
    }

    public function getPostalCode(): string
    {
        return $this->data['postalCode'];
    }

    public function getFullAddress(): string
    {
        return sprintf(
            '%s, %s %s',
            $this->getStreet(),
            $this->getPostalCode(),
            $this->getCity()
        );
    }

    // === Tax info ===

    public function getRegistrationNumber(): string
    {
        return $this->data['registrationNumber'];
    }

    public function getVatNumber(): string
    {
        return $this->data['vatNumber'];
    }

    public function isVatPayer(): bool
    {
        return $this->data['vatPayer'];
    }

    // === Registry ===

    public function getRegistryCity(): string
    {
        return $this->data['registryCity'];
    }

    public function getRegistryNumber(): string
    {
        return $this->data['registryNumber'];
    }

    // === Banking ===

    public function getBankAccount(): ?string
    {
        return $this->data['bankAccount'];
    }

    public function getBankAccountIban(): ?string
    {
        return $this->data['bankAccountIban'];
    }

    public function getBankAccountBic(): ?string
    {
        return $this->data['bankAccountBic'];
    }

    // === Payment gateway ===

    public function getGopayConfigRaw(): ?string
    {
        return $this->data['gopayConfig'];
    }

    public function getGopayConfig(): ?array
    {
        $json = $this->data['gopayConfig'];

        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    // === Business operations ===

    public function hasExternalSale(): bool
    {
        return $this->data['externalSale'];
    }

    /**
     * Get supplier text for invoice
     * TODO: Build dynamically from company details instead of using DB field
     */
    public function getSupplierText(): ?string
    {
        return $this->data['supplier'] ?? null;
    }

    // === Magic getter for rarely used or future fields ===

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    // === Factory ===

    public static function createFromData(array $data): self
    {
        return new self($data['id'], $data);
    }
}