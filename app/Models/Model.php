<?php

namespace App\Models;

use PDO;
use PDOException;

abstract class Model {
    protected static ?PDO $pdo = null;
    protected string $table;

    public function __construct() {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../../config/database.php';
            $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            } catch (PDOException $e) {
                // In a real application, log this error and show a user-friendly message
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
    }

    /**
     * Get the PDO instance.
     * @return PDO
     */
    public static function db(): PDO {
        if (self::$pdo === null) {
            new static(); // Initialize connection if not already done
        }
        return self::$pdo;
    }

    /**
     * Find a record by its ID.
     * @param int $id
     * @return static|null
     */
    public static function find(int $id): ?static {
        $stmt = self::db()->prepare("SELECT * FROM " . (new static())->table . " WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $model = new static();
            foreach ($data as $key => $value) {
                if (property_exists($model, $key)) {
                    $model->{$key} = $value;
                }
            }
            return $model;
        }
        return null;
    }

    /**
     * Get all records from the table.
     * @param string $orderBy Example: "created_at DESC"
     * @return array
     */
    public static function all(string $orderBy = ''): array {
        $sql = "SELECT * FROM " . (new static())->table;
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . $orderBy; // Be cautious with user input here
        }
        $stmt = self::db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Create a new record.
     * @param array $data Associative array of column => value
     * @return int|false The ID of the newly created record or false on failure.
     */
    public static function create(array $data): int|false {
        if (empty($data)) {
            return false;
        }
        $table = (new static())->table;
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $stmt = self::db()->prepare($sql);
        try {
            $stmt->execute($data);
            return (int)self::db()->lastInsertId();
        } catch (PDOException $e) {
            // Log error: $e->getMessage();
            return false;
        }
    }

    /**
     * Update an existing record by ID.
     * @param int $id
     * @param array $data Associative array of column => value
     * @return bool True on success, false on failure.
     */
    public function update(array $data): bool {
        if (empty($data) || !isset($this->id)) {
            return false;
        }
        $table = $this->table;
        $setClauses = [];
        foreach (array_keys($data) as $key) {
            $setClauses[] = "{$key} = :{$key}";
        }
        $setClause = implode(", ", $setClauses);
        $sql = "UPDATE {$table} SET {$setClause} WHERE id = :id";

        $stmt = self::db()->prepare($sql);
        $data['id'] = $this->id; // Add id to data array for binding

        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            // Log error: $e->getMessage();
            return false;
        }
    }

    /**
     * Save the current model instance (creates if new, updates if exists).
     * @return bool True on success, false on failure
     */
    public function save(): bool {
        $data = (array) $this; // Get public properties
        unset($data['table'], $data['pdo']); // Remove non-column properties

        if (isset($this->id) && !empty($this->id)) {
            // Update existing record
            if (empty($data)) return true; // Nothing to update

            $setParts = [];
            foreach (array_keys($data) as $key) {
                if ($key === 'id') continue; // Don't include id in SET part
                $setParts[] = "{$key} = :{$key}";
            }
            if (empty($setParts)) return true;

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = self::db()->prepare($sql);
            return $stmt->execute($data);

        } else {
            // Create new record
            unset($data['id']); // Remove id if it's null or empty, as it's auto-increment
            if (empty($data)) return false;

            $keys = array_keys($data);
            $columns = implode(', ', $keys);
            $placeholders = ':' . implode(', :', $keys);
            $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
            $stmt = self::db()->prepare($sql);
            $result = $stmt->execute($data);
            if ($result) {
                $this->id = (int)self::db()->lastInsertId();
            }
            return $result;
        }
    }


    /**
     * Delete a record by its ID.
     * @param int $id
     * @return bool True on success, false on failure.
     */
    public static function delete(int $id): bool {
        $stmt = self::db()->prepare("DELETE FROM " . (new static())->table . " WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error
            return false;
        }
    }

    /**
     * Execute a raw query.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Parameters to bind to the query.
     * @param bool $fetchOne Whether to fetch a single row.
     * @return mixed The result set (array of objects or single object) or statement object.
     */
    protected static function query(string $sql, array $params = [], bool $fetchOne = false): mixed
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);

        if (stripos(trim($sql), 'SELECT') === 0) {
            if ($fetchOne) {
                return $stmt->fetchObject(static::class);
            }
            return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
        }
        return $stmt; // For INSERT, UPDATE, DELETE, return statement for rowCount etc.
    }
}
