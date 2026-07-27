<?php

namespace App\Services\Admin;

use App\Models\Attribute;
use App\Enums\GeneralStatus;

class AttributeService
{
    public function getAttributesByFields(array $fieldIds, ?string $search = null)
    {
        $query = Attribute::with('field:id,name');

        if (!empty($fieldIds)) {
            $query->whereIn('fk_field_id', $fieldIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('type', 'LIKE', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function getAttributeTypes(array $fieldIds): array
    {
        $query = Attribute::query();

        if (!empty($fieldIds)) {
            $query->whereIn('fk_field_id', $fieldIds);
        }

        return $query->distinct()->pluck('type')->filter()->values()->toArray();
    }

    public function createAttribute(array $data): Attribute
    {
        return Attribute::create([
            'fk_field_id' => $data['fk_field_id'],
            'name'        => $data['name'],
            'type'        => strtolower($data['type']),
            'stock'       => $data['stock'],
            'price_hour'  => $data['price_hour'],
            'status'      => GeneralStatus::ACTIVE->value,
        ]);
    }

    public function updateAttribute(Attribute $attribute, array $data): Attribute
    {
        if (isset($data['type'])) {
            $data['type'] = strtolower($data['type']);
        }
        $attribute->update($data);
        return $attribute->fresh();
    }

    public function toggleAttributeStatus(Attribute $attribute): Attribute
    {
        $newStatus = ($attribute->status === GeneralStatus::ACTIVE->value)
            ? GeneralStatus::INACTIVE->value
            : GeneralStatus::ACTIVE->value;

        $attribute->update(['status' => $newStatus]);
        return $attribute->fresh();
    }
}
