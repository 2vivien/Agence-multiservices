<?php

namespace App\Models;

use PDO;

class OperationType extends Model {
    protected string $table = 'operation_types';
    public ?int $id = null;
    public string $name;
    public ?string $description = null;
    public bool $is_active = true;
    public string $balance_effect = 'neutral'; // 'positive', 'negative', 'neutral'
    public ?string $category = null;
    public bool $is_commission = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get all active operation types.
     * @return array An array of OperationType objects.
     */
    public static function getActiveOperationTypes(): array {
        return self::query("SELECT * FROM " . (new static())->table . " WHERE is_active = TRUE ORDER BY name ASC");
    }

    /**
     * Get all operation types (both active and inactive).
     * @return array An array of OperationType objects.
     */
    public static function getAllOperationTypes(): array {
        return self::all("name ASC");
    }

    /**
     * Get operations of this type.
     * @return array
     */
    public function operations(): array {
        if (!$this->id) return [];
        return Operation::query("SELECT * FROM operations WHERE operation_type_id = :operation_type_id", ['operation_type_id' => $this->id]);
    }
}
