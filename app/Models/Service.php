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

    /**
     * Find a service by its name.
     * @param string $name
     * @return Service|null
     */
    public static function findByName(string $name): ?Service {
        $stmt = self::db()->prepare("SELECT * FROM " . (new static())->table . " WHERE name = :name");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            // Basic manual hydration
            $service = new static();
            $service->id = (int)$data['id'];
            $service->name = $data['name'];
            $service->description = $data['description'];
            $service->is_active = (bool)$data['is_active'];
            $service->default_commission_rate = isset($data['default_commission_rate']) ? (float)$data['default_commission_rate'] : null;
            $service->created_at = $data['created_at'];
            $service->updated_at = $data['updated_at'];
            return $service;
        }
        return null;
    }

    /**
     * Create a new service.
     * @param array $data (name, description, default_commission_rate, is_active)
     * @return Service|null The created Service object or null on failure.
     */
    public static function createService(array $data): ?Service {
        if (empty($data['name'])) {
            return null; // Name is mandatory
        }

        // Optional: Check for name uniqueness before attempting insert
        // if (self::findByName($data['name'])) { return null; /* Or throw Exception */ }

        $db = self::db();
        $sql = "INSERT INTO " . (new static())->table . " (name, description, default_commission_rate, is_active, created_at, updated_at)
                VALUES (:name, :description, :default_commission_rate, :is_active, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':default_commission_rate' => $data['default_commission_rate'] ?? null,
            ':is_active' => $data['is_active'] ?? true,
        ]);

        if ($success) {
            $id = $db->lastInsertId();
            return parent::find((int)$id); // Use parent::find for consistent hydration
        }
        return null;
    }

    /**
     * Update an existing service.
     * @param int $id The ID of the service.
     * @param array $data Data to update (name, description, default_commission_rate, is_active).
     * @return bool True on success, false otherwise.
     */
    public static function updateService(int $id, array $data): bool {
        if (empty($data) || !$id) {
            return false;
        }

        $fields = [];
        $params = [':id' => $id];
        $allowedFields = ['name', 'description', 'default_commission_rate', 'is_active'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = ($field === 'is_active') ? (int)filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) : $data[$field];
            }
        }

        if (empty($fields)) {
            return false; // No valid fields to update
        }
        $fields[] = "updated_at = NOW()";

        $sql = "UPDATE " . (new static())->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a service.
     * Note: Consider deactivation (is_active = false) instead of hard delete if operations are linked.
     * @param int $id
     * @return bool
     */
    public static function deleteService(int $id): bool {
        // Check for linked operations first might be a good idea if not using FK constraints that prevent deletion.
        $sql = "DELETE FROM " . (new static())->table . " WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get services with optional filters and pagination.
     * @param array $filters ['is_active' => true/false]
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getServices(array $filters = [], int $limit = 10, int $offset = 0): array {
        $params = [':limit' => $limit, ':offset' => $offset];
        $whereClauses = [];

        if (isset($filters['is_active'])) {
            $whereClauses[] = "is_active = :is_active";
            $params[':is_active'] = (int)filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        // Add more filters if needed

        $sql = "SELECT * FROM " . (new static())->table;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";

        $stmt = self::db()->prepare($sql);
        foreach ($params as $paramKey => $value) {
             $stmt->bindValue($paramKey, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_CLASS, static::class);

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM " . (new static())->table;
        if (!empty($whereClauses)) {
            $countSql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        // Remove limit/offset params for count query
        $countParams = $params;
        unset($countParams[':limit'], $countParams[':offset']);

        $countStmt = self::db()->prepare($countSql);
        foreach ($countParams as $paramKey => $value) {
             $countStmt->bindValue($paramKey, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        return ['data' => $services, 'total' => $totalRecords];
    }

    /**
     * Deactivate a service by setting is_active to false.
     * @param int $id The ID of the service to deactivate.
     * @return bool True on success, false on failure.
     */
    public static function deactivateService(int $id): bool {
        $sql = "UPDATE " . (new static())->table . " SET is_active = FALSE, updated_at = NOW() WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
