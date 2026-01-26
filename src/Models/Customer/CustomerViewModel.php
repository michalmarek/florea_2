<?php declare(strict_types=1);

namespace Models\Customer;

class CustomerViewModel
{
    private ?Customer $entity = null;

    public function __construct(
        public readonly bool $isLoggedIn,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        private readonly ?CustomerAuthService $authService = null,
    ) {}

    /**
     * Get full customer entity (lazy loaded - executes SQL only when called)
     */
    public function getEntity(): ?Customer
    {
        if (!$this->isLoggedIn) {
            return null;
        }

        if ($this->entity === null && $this->authService !== null) {
            $this->entity = $this->authService->getCurrentCustomer();
        }

        return $this->entity;
    }

    /**
     * Magic getter for entity properties
     * Session properties (isLoggedIn, firstName, lastName) - NO SQL
     * Other properties - lazy loads entity (1x SQL, then cached)
     */
    public function __get(string $name)
    {
        // Session data - direct access, no SQL
        if (in_array($name, ['isLoggedIn', 'firstName', 'lastName'])) {
            return $this->$name;
        }

        // Entity data - lazy load
        return $this->getEntity()?->$name;
    }
}