<?php

namespace App\Models;

use PDO;

class Service extends Model {
    protected string $table = 'services';
    public ?int $id = null;
    public string $name;
    public ?string $description = null;
    public bool $is_active = true;
    public ?float $default_commission_rate = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get all active services.
     * @return array An array of Service objects.
     */
    public static function getActiveServices(): array {
        return self::query("SELECT * FROM " . (new static())->table . " WHERE is_active = TRUE ORDER BY name ASC");
    }

    /**
     * Get all services (both active and inactive).
     * @return array An array of Service objects.
     */
    public static function getAllServices(): array {
        return self::all("name ASC");
    }

    /**
     * Get operations associated with this service.
     * @return array
     */
    public function operations(): array {
        if (!$this->id) return [];
        return Operation::query("SELECT * FROM operations WHERE service_id = :service_id", ['service_id' => $this->id]);
    }

    /**
     * Get balances associated with this service.
     * @return array
     */
    public function balances(): array {
        if (!$this->id) return [];
        return Balance::query("SELECT * FROM balances WHERE service_id = :service_id", ['service_id' => $this->id]);
    }
}
