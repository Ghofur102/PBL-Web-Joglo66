<?php

namespace Tests\Unit\Services\Admin;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use Tests\Unit\Services\ServiceTestCase;
use Tests\Unit\Services\ServiceTestData;
use App\Services\Admin\AttributeService;

class AttributeServiceTest extends ServiceTestCase
{
    public function test_get_attributes_by_fields(): void {
        // **setup test data
        // Create a user, a field, and two attributes
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $ball = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::FIVE, ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);
        $otherBall = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::TEN, ServiceTestData::ONE_THOUSAND, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);
        $vest = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_VEST, ServiceTestData::THREE, ServiceTestData::ONE_THOUSAND, type: ServiceTestData::TYPE_ATTRIBUTE_PAKAIAN);

        // Create other field and attribute to ensure they are not included in the results
        $otherField = $this->createField($owner, ServiceTestData::FIELD_SECONDARY);
        $otherAttributeBall = $this->createAttribute($otherField, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::TEN, ServiceTestData::TWO_THOUSAND, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);
        $otherAttributeVest = $this->createAttribute($otherField, ServiceTestData::ATTRIBUTE_VEST, ServiceTestData::FIVE, ServiceTestData::ONE_THOUSAND, type: ServiceTestData::TYPE_ATTRIBUTE_PAKAIAN);

        // Create id fields array and search
        $fieldIds = [$field->id];
        $search_name = ServiceTestData::ATTRIBUTE_BALL;
        $search_type = ServiceTestData::TYPE_ATTRIBUTE_PERALATAN;

        // **check the service method for getting attributes by name and type
        $search_attributes_by_type = app(AttributeService::class)->getAttributesByFields($fieldIds, $search_type);
        $search_attributes_by_name = app(AttributeService::class)->getAttributesByFields($fieldIds, $search_name);

        // positive test
        $this->assertCount(2, $search_attributes_by_name);

        $this->assertContains($field->id, $search_attributes_by_name->pluck('fk_field_id')->all());
        $this->assertContains($field->name, $search_attributes_by_name->pluck('field.name')->all());

        $this->assertContains($ball->id, $search_attributes_by_name->pluck('id')->all());
        $this->assertContains($ball->name, $search_attributes_by_name->pluck('name')->all());
        $this->assertContains($ball->type, $search_attributes_by_name->pluck('type')->all());
        $this->assertContains($ball->stock, $search_attributes_by_name->pluck('stock')->all());
        $this->assertContains($ball->price_hour, $search_attributes_by_name->pluck('price_hour')->all());
        $this->assertContains($ball->status, $search_attributes_by_name->pluck('status')->all());

        $this->assertContains($otherBall->id, $search_attributes_by_name->pluck('id')->all());
        $this->assertContains($otherBall->name, $search_attributes_by_name->pluck('name')->all());
        $this->assertContains($otherBall->type, $search_attributes_by_name->pluck('type')->all());
        $this->assertContains($otherBall->stock, $search_attributes_by_name->pluck('stock')->all());
        $this->assertContains($otherBall->price_hour, $search_attributes_by_name->pluck('price_hour')->all());
        $this->assertContains($otherBall->status, $search_attributes_by_name->pluck('status')->all());

        // negative test
        $hasVestId = $search_attributes_by_name->contains('id', $vest->id);
        $this->assertFalse($hasVestId, "Vest ID ternyata ditemukan di dalam kriteria attributes.");

        $hasOtherFieldId = $search_attributes_by_name->contains('fk_field_id', $otherField->id);
        $this->assertFalse($hasOtherFieldId, "Field ID lain ternyata ditemukan di dalam kriteria attributes.");

        $hasOtherAttributeId = $search_attributes_by_name->contains('id', $otherAttributeBall->id);
        $this->assertFalse($hasOtherAttributeId, "Attribute ID lain ternyata ditemukan di dalam data attributes.");

        $this->assertCount(2, $search_attributes_by_type);
        $this->assertNotContains($vest->id, $search_attributes_by_type->pluck('id')->all());
        $this->assertNotContains($otherAttributeVest->id, $search_attributes_by_type->pluck('id')->all());
    }

    public function test_get_attribute_types(): void {
        // **setup test data
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $otherField = $this->createField($owner, ServiceTestData::FIELD_SECONDARY);
        $ball = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::FIVE, ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);
        $otherVest = $this->createAttribute($otherField, ServiceTestData::ATTRIBUTE_VEST, ServiceTestData::FIVE, ServiceTestData::ONE_THOUSAND, type: ServiceTestData::TYPE_ATTRIBUTE_PAKAIAN);

        // positive test
        $search_types = app(AttributeService::class)->getAttributeTypes([$field->id]);
        $this->assertCount(1, $search_types);
        $this->assertContains($ball->type, $search_types);

        // negative test
        $this->assertNotContains($otherVest->type, $search_types);
    }

    public function test_create_attribute(): void {
        // **setup test data
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $name = ServiceTestData::ATTRIBUTE_BALL;
        $type = ServiceTestData::TYPE_ATTRIBUTE_PERALATAN;
        $stock = ServiceTestData::FIVE;
        $price_hour = ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED;
        $data = [
            'fk_field_id' => $field->id,
            'name'        => $name,
            'type'        => $type,
            'stock'       => $stock,
            'price_hour'  => $price_hour,
        ];

        // positive test
        $create_attribute = app(AttributeService::class)->createAttribute($data);
        $this->assertDatabaseCount('attributes', 1);
        $this->assertDatabaseHas('attributes', [
            'id'          => $create_attribute->id,
            'fk_field_id' => $field->id,
            'name'        => $name,
            'type'        => strtolower($type),
            'stock'       => $stock,
            'price_hour'  => $price_hour,
            'status'      => ServiceTestData::GENERAL_ACTIVE,
        ]);
    }

    public function test_update_attribute(): void {
        // **setup test data
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $ball = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::FIVE, ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);
        $name = ServiceTestData::ATTRIBUTE_BALL;
        $type = ServiceTestData::TYPE_ATTRIBUTE_PERALATAN;
        $stock = ServiceTestData::TEN;
        $price_hour = ServiceTestData::THREE_THOUSAND;
        $data = [
            'fk_field_id' => $field->id,
            'name'        => $name,
            'type'        => $type,
            'stock'       => $stock,
            'price_hour'  => $price_hour,
        ];
        $update_attribute = app(AttributeService::class)->updateAttribute($ball, $data);

        // positive test
        $this->assertDatabaseCount('attributes', 1);
        $this->assertDatabaseHas('attributes', [
            'id'          => $update_attribute->id,
            'fk_field_id' => $field->id,
            'name'        => $update_attribute->name,
            'type'        => strtolower($update_attribute->type),
            'stock'       => $update_attribute->stock,
            'price_hour'  => $update_attribute->price_hour,
            'status'      => ServiceTestData::GENERAL_ACTIVE,
        ]);

        // negative test
        $this->assertDatabaseMissing('attributes', [
            'id'          => $ball->id,
            'fk_field_id' => $field->id,
            'name'        => ServiceTestData::ATTRIBUTE_BALL,
            'type'        => strtolower(ServiceTestData::TYPE_ATTRIBUTE_PERALATAN),
            'stock'       => ServiceTestData::FIVE,
            'price_hour'  => ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED,
            'status'      => ServiceTestData::GENERAL_ACTIVE,
        ]);
    }

    public function test_toggle_attribute_status(): void {
        // **setup test data
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $ball = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::FIVE, ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED, type: ServiceTestData::TYPE_ATTRIBUTE_PERALATAN);

        // positive test
        $this->assertDatabaseCount('attributes', 1);
        $this->assertSame(ServiceTestData::GENERAL_ACTIVE, $ball->status);
        $this->assertDatabaseHas('attributes', [
            'id'          => $ball->id,
            'fk_field_id' => $field->id,
            'name'        => $ball->name,
            'type'        => strtolower($ball->type),
            'stock'       => $ball->stock,
            'price_hour'  => $ball->price_hour,
            'status'      => ServiceTestData::GENERAL_ACTIVE,
        ]);

        $toggle_attribute_status = app(AttributeService::class)->toggleAttributeStatus($ball);

        // negative test
        $this->assertDatabaseCount('attributes', 1);
        $this->assertSame(ServiceTestData::GENERAL_INACTIVE, $toggle_attribute_status->status);
        $this->assertDatabaseMissing('attributes', [
            'id'          => $ball->id,
            'fk_field_id' => $field->id,
            'name'        => $ball->name,
            'type'        => strtolower($ball->type),
            'stock'       => $ball->stock,
            'price_hour'  => $ball->price_hour,
            'status'      => ServiceTestData::GENERAL_ACTIVE,
        ]);
    }
}
