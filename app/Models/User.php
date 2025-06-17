<?php

namespace App\Models;

use PDO;

class User extends Model {
    protected string $table = 'users';
    public ?int $id = null;
    public string $username;
    public string $password_hash;
    public string $full_name;
    public string $role; // 'gerant', 'admin'
    public bool $is_active;
    public ?string $created_at = null;
    public ?string $updated_at = null;

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
            $user->role = $data['role'];
            $user->is_active = (bool)$data['is_active'];
            $user->created_at = $data['created_at'];
            $user->updated_at = $data['updated_at'];
            return $user;
        }
        return null;
    }

    /**
     * Verify password for the user.
     * In a real app, use password_verify against a hashed password.
     * This is a placeholder.
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool {
        // Replace this with actual password_verify logic
        // For example: return password_verify($password, $this->password_hash);
        // For seed data like 'hashed_password_admin', this will always fail.
        // This method needs to be implemented correctly when actual hashing is done.
        if ($this->password_hash === 'hashed_password_admin' && $password === 'admin_password_for_test') {
            return true;
        }
        if ($this->password_hash === 'hashed_password_gerant01' && $password === 'gerant01_password_for_test') {
            return true;
        }
        // This is a simplified check for demonstration with unhashed seed passwords
        return ($this->password_hash === $password || $this->password_hash === "hashed_".$password);
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
}
