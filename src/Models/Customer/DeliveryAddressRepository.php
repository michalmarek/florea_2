<?php declare(strict_types=1);

namespace Models\Customer;

use Core\Database;
use Nette\Database\Table\Selection;

class DeliveryAddressRepository
{
    /**
     * Find all addresses for customer
     *
     * @param int $customerId
     * @return array<DeliveryAddress>
     */
    public function findByCustomerId(int $customerId): array
    {
        $rows = Database::table('es_dodaci')
            ->where('uzivatel', $customerId)
            ->order('is_default DESC, name ASC');

        return $this->mapRowsToEntities($rows);
    }

    /**
     * Find address by ID
     *
     * @param int $id
     * @return DeliveryAddress|null
     */
    public function findById(int $id): ?DeliveryAddress
    {
        $row = Database::table('es_dodaci')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Create new address
     *
     * @param array $data
     * @return DeliveryAddress
     */
    public function create(array $data): DeliveryAddress
    {
        // If this should be default, unset other defaults first
        if ($data['isDefault'] ?? false) {
            $this->unsetDefaultForCustomer($data['customerId']);
        }

        $row = Database::table('es_dodaci')->insert([
            'uzivatel' => $data['customerId'],
            'name' => $data['name'],
            'is_default' => $data['isDefault'] ? 1 : 0,
            'contact' => 0, // Not used for now
            'firma' => $data['companyName'] ?? '',
            'jmeno' => $data['firstName'],
            'ulice' => $data['street'],
            'mesto' => $data['city'],
            'psc' => $data['postalCode'],
            'country' => $data['country'] ?? 'cz',
            'predvolba' => $data['phonePrefix'] ?? '',
            'telefon' => $data['phone'] ?? '',
            'gps_lat' => $data['gpsLat'] ?? 0,
            'gps_lon' => $data['gpsLon'] ?? 0,
            'poznamka_kuryr' => $data['courierNote'] ?? '',
            'oteviraci_doba' => $data['openingHours'] ?? '',
            'morava' => '0',
            'prefilled' => 0,
        ]);

        return $this->mapToEntity($row);
    }

    /**
     * Update address
     *
     * @param int $id
     * @param array $data
     * @return DeliveryAddress
     */
    public function update(int $id, array $data): DeliveryAddress
    {
        // If this should be default, unset other defaults first
        if (isset($data['isDefault']) && $data['isDefault']) {
            $address = $this->findById($id);
            if ($address) {
                $this->unsetDefaultForCustomer($address->customerId);
            }
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['isDefault'])) {
            $updateData['is_default'] = $data['isDefault'] ? 1 : 0;
        }
        if (isset($data['companyName'])) {
            $updateData['firma'] = $data['companyName'];
        }
        if (isset($data['firstName'])) {
            $updateData['jmeno'] = $data['firstName'];
        }
        if (isset($data['street'])) {
            $updateData['ulice'] = $data['street'];
        }
        if (isset($data['city'])) {
            $updateData['mesto'] = $data['city'];
        }
        if (isset($data['postalCode'])) {
            $updateData['psc'] = $data['postalCode'];
        }
        if (isset($data['country'])) {
            $updateData['country'] = $data['country'];
        }
        if (isset($data['phonePrefix'])) {
            $updateData['predvolba'] = $data['phonePrefix'];
        }
        if (isset($data['phone'])) {
            $updateData['telefon'] = $data['phone'];
        }
        if (isset($data['courierNote'])) {
            $updateData['poznamka_kuryr'] = $data['courierNote'];
        }
        if (isset($data['openingHours'])) {
            $updateData['oteviraci_doba'] = $data['openingHours'];
        }

        Database::table('es_dodaci')
            ->where('id', $id)
            ->update($updateData);

        return $this->findById($id);
    }

    /**
     * Delete address
     *
     * @param int $id
     * @return void
     */
    public function delete(int $id): void
    {
        Database::table('es_dodaci')
            ->where('id', $id)
            ->delete();
    }

    /**
     * Set address as default
     *
     * @param int $id
     * @return void
     */
    public function setAsDefault(int $id): void
    {
        $address = $this->findById($id);
        if ($address) {
            $this->unsetDefaultForCustomer($address->customerId);

            Database::table('es_dodaci')
                ->where('id', $id)
                ->update(['is_default' => 1]);
        }
    }

    /**
     * Unset default flag for all customer addresses
     *
     * @param int $customerId
     * @return void
     */
    private function unsetDefaultForCustomer(int $customerId): void
    {
        Database::table('es_dodaci')
            ->where('uzivatel', $customerId)
            ->update(['is_default' => 0]);
    }

    /**
     * Map database rows to entities
     *
     * @param Selection $rows
     * @return array<DeliveryAddress>
     */
    private function mapRowsToEntities(Selection $rows): array
    {
        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $this->mapToEntity($row);
        }
        return $entities;
    }

    /**
     * Map single row to entity
     *
     * @param \Nette\Database\Table\ActiveRow $row
     * @return DeliveryAddress
     */
    private function mapToEntity($row): DeliveryAddress
    {
        return new DeliveryAddress(
            id: $row->id,
            customerId: $row->uzivatel,
            name: $row->name,
            isDefault: (bool) $row->is_default,
            companyName: $row->firma ?: null,
            firstName: $row->jmeno,
            street: $row->ulice,
            city: $row->mesto,
            postalCode: $row->psc,
            country: $row->country,
            phone: $row->telefon ?: null,
            gpsLat: $row->gps_lat ? (float) $row->gps_lat : null,
            gpsLon: $row->gps_lon ? (float) $row->gps_lon : null,
            courierNote: $row->poznamka_kuryr ?: null,
            openingHours: $row->oteviraci_doba ?: null,
        );
    }
}