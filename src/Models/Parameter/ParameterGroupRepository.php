<?php declare(strict_types=1);

namespace Models\Parameter;

use Core\Database;

class ParameterGroupRepository
{
    public function findById(int $id): ?ParameterGroup
    {
        $query = "
            SELECT id, name, freeText, freeInteger, units
            FROM es_parameterGroups
            WHERE id = ?
        ";

        $row = Database::query($query, $id)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    private function mapToEntity(object $row): ParameterGroup
    {
        return new ParameterGroup(
            id: (int) $row->id,
            name: $row->name,
            isFreeText: $row->freeText === 1,
            isFreeInteger: $row->freeInteger === 1,
            units: $row->units,
        );
    }
}