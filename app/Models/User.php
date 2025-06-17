<?php

namespace App\Models;

use PDO;

class User extends Model {
    protected string $table = 'users';
    public ?int $id = null;
    public string $username;
    public string $password_hash;
    public string $full_name;
    public ?string $email = null; // Added email property
    public string $role; // 'gerant', 'admin'
    public bool $is_active;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Sets and hashes the user's password.
     * @param string $plainPassword The plain text password to hash.
     */
    public function setPassword(string $plainPassword): void {
        $this->password_hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    // Constructor could be added if specific initialization is needed
    // public function __construct(...) {
    //     parent::__construct();
    //     // ... initialization ...
    // }

    /**
     * Find a user by username.
     * @param string $username
     * @return User|null
     */
    public static function findByUsername(string $username): ?User {
        $stmt = self::db()->prepare("SELECT * FROM " . (new static())->table . " WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $user = new User();
            $user->id = (int)$data['id'];
            $user->username = $data['username'];
            $user->password_hash = $data['password_hash'];
            $user->full_name = $data['full_name'];
            $user->email = $data['email'] ?? null;
            $user->role = $data['role'];
            $user->is_active = (bool)$data['is_active'];
            $user->created_at = $data['created_at'];
            $user->updated_at = $data['updated_at'];
            return $user;
        }
        return null;
    }

    /**
     * Verify the given password against the stored hash.
     * @param string $plainPassword The plain text password to verify.
     * @return bool True if the password matches, false otherwise.
     */
    public function verifyPassword(string $plainPassword): bool {
        return password_verify($plainPassword, $this->password_hash);
    }

    /**
     * Get all operations for this user.
     * @return array
     */
    public function operations(): array {
        return Operation::query("SELECT * FROM operations WHERE user_id = :user_id ORDER BY operation_time DESC", ['user_id' => $this->id]);
    }

    /**
     * Get all balances for this user.
     * @return array
     */
    public function balances(): array {
        return Balance::query("SELECT * FROM balances WHERE user_id = :user_id ORDER BY balance_date DESC", ['user_id' => $this->id]);
    }

    /**
     * Get all active gérants.
     * @return array
     */
    public static function getActiveGerants(): array {
        return self::query("SELECT * FROM " . (new static())->table . " WHERE role = 'gerant' AND is_active = TRUE ORDER BY full_name ASC");
    }

    /**
     * Get all users (both active and inactive).
     * @return array
     */
    public static function getAllUsers(): array {
        return self::all("full_name ASC");
    }

    /**
     * Count active users by a specific role.
     * @param string $role The role to filter by (e.g., 'gerant', 'admin').
     * @return int The number of active users with that role.
     */
    public static function countActiveUsersByRole(string $role): int {
        $sql = "SELECT COUNT(*) as count FROM " . (new static())->table . " WHERE role = :role AND is_active = TRUE";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Find a user by email.
     * @param string $email
     * @return User|null
     */
    public static function findByEmail(string $email): ?User {
        $stmt = self::db()->prepare("SELECT * FROM " . (new static())->table . " WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $user = new static(); // Use static for late static binding
            // Manual hydration
            $user->id = (int)$data['id'];
            $user->username = $data['username'];
            $user->password_hash = $data['password_hash'];
            $user->full_name = $data['full_name'];
            $user->role = $data['role'];
            $user->is_active = (bool)$data['is_active'];
            $user->created_at = $data['created_at'];
            $user->updated_at = $data['updated_at'];
            // $user->email was added above, so this specific check might be redundant if findByEmail always populates it.
            // However, if findByUsername (called by parent::find() in createUser) doesn't fetch email, it would be an issue.
            // For now, direct assignment in findByEmail and findByUsername is fine.
            return $user;
        }
        return null;
    }

    /**
     * Create a new user.
     * @param array $data Associative array of user data (username, full_name, email, role, is_active).
     * @param string $plainPassword The plain text password.
     * @return User|null The created User object or null on failure.
     */
    public static function createUser(array $data, string $plainPassword): ?User {
        if (empty($data['username']) || empty($data['full_name']) || empty($plainPassword) || empty($data['role'])) {
            return null; // Essential fields missing
        }

        $user = new static();
        $user->username = $data['username'];
        $user->full_name = $data['full_name'];
        $user->email = $data['email'] ?? null; // Assuming email property exists or will be added
        $user->role = $data['role'];
        $user->is_active = $data['is_active'] ?? true;
        $user->setPassword($plainPassword); // Hash the password

        $db = self::db();
        $sql = "INSERT INTO " . $user->table . " (username, password_hash, full_name, email, role, is_active, created_at, updated_at)
                VALUES (:username, :password_hash, :full_name, :email, :role, :is_active, NOW(), NOW())";
        $stmt = $db->prepare($sql);

        $success = $stmt->execute([
            ':username' => $user->username,
            ':password_hash' => $user->password_hash,
            ':full_name' => $user->full_name,
            ':email' => $user->email,
            ':role' => $user->role,
            ':is_active' => (int)$user->is_active, // Cast boolean to int for DB
        ]);

        if ($success) {
            $id = $db->lastInsertId();
            return parent::find((int)$id); // Use parent::find to get hydrated User object
        }
        return null;
    }

    /**
     * Update an existing user.
     * @param int $id The ID of the user to update.
     * @param array $data Associative array of data to update (can include: full_name, email, role, is_active, username).
     * @return bool True on success, false on failure.
     */
    public static function updateUser(int $id, array $data): bool {
        if (empty($data) || !$id) {
            return false;
        }

        $fields = [];
        $params = [':id' => $id];
        // Allowed fields for update (password is not updated here)
        $allowedFields = ['full_name', 'email', 'role', 'is_active', 'username'];

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
     * Delete a user (hard delete).
     * Consider soft delete (marking as inactive) for real applications.
     * @param int $id The ID of the user to delete.
     * @return bool True on success, false on failure.
     */
    public static function deleteUser(int $id): bool {
        // Add check: cannot delete own account or last admin?
        $sql = "DELETE FROM " . (new static())->table . " WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get users with optional filters and pagination.
     * @param array $filters Filters like ['role' => 'gerant', 'is_active' => true].
     * @param int $limit
     * @param int $offset
     * @return array Array of User objects.
     */
    public static function getUsers(array $filters = [], int $limit = 10, int $offset = 0): array {
        $params = [':limit' => $limit, ':offset' => $offset];
        $whereClauses = [];

        if (isset($filters['role'])) {
            $whereClauses[] = "role = :role";
            $params[':role'] = $filters['role'];
        }
        if (isset($filters['is_active'])) {
            $whereClauses[] = "is_active = :is_active";
            $params[':is_active'] = (int)filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        // Add more filters like username search, email search etc. if needed
        // if (isset($filters['username_like'])) {
        //     $whereClauses[] = "username LIKE :username_like";
        //     $params[':username_like'] = "%" . $filters['username_like'] . "%";
        // }

        $sql = "SELECT * FROM " . (new static())->table;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY full_name ASC LIMIT :limit OFFSET :offset";

        $stmt = self::db()->prepare($sql);
        foreach ($params as $paramKey => $value) {
             $stmt->bindValue($paramKey, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Deactivate a user by setting is_active to false.
     * @param int $id The ID of the user to deactivate.
     * @return bool True on success, false on failure.
     */
    public static function deactivateUser(int $id): bool {
        $sql = "UPDATE " . (new static())->table . " SET is_active = FALSE, updated_at = NOW() WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
