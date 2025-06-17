<?php

namespace Tests\Unit;

use Tests\TestCase; // Our base TestCase
use App\Models\User; // The User model we are testing

class UserTest extends TestCase
{
    public function test_set_password_hashes_password_correctly(): void
    {
        $user = new User();
        $plainPassword = 'password123';

        $user->setPassword($plainPassword);

        $this->assertNotEmpty($user->password_hash, "Password hash should not be empty.");
        $this->assertNotEquals($plainPassword, $user->password_hash, "Hashed password should not be the same as plain password.");

        // Verify that the hash matches the plain password
        $this->assertTrue(
            password_verify($plainPassword, $user->password_hash),
            "Password_verify should confirm the hash matches the plain password."
        );

        // Optional: Check if a different plain password does NOT match the hash
        $this->assertFalse(
            password_verify('wrongpassword', $user->password_hash),
            "Password_verify should reject a wrong password against the hash."
        );
    }

    public function test_verify_password_works_correctly(): void
    {
        $user = new User();
        $plainPassword = 'securePassword!@#';

        // Set the password hash (simulating a stored user)
        $user->password_hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Test with correct password
        $this->assertTrue(
            $user->verifyPassword($plainPassword),
            "verifyPassword should return true for the correct password."
        );

        // Test with incorrect password
        $this->assertFalse(
            $user->verifyPassword('incorrectPassword'),
            "verifyPassword should return false for an incorrect password."
        );

        // Test with empty password against a valid hash
        $this->assertFalse(
            $user->verifyPassword(''),
            "verifyPassword should return false for an empty password against a valid hash."
        );
    }

    public function test_verify_password_with_empty_hash_returns_false(): void
    {
        $user = new User();
        // $user->password_hash is not set (null by default if not typed, or empty string)
        // Depending on User model initialization, ensure it's null or empty for this test
        if (property_exists($user, 'password_hash')) { // Check if property exists before assigning
             $user->password_hash = ''; // Explicitly set to empty string for test clarity
        } else {
            // If password_hash is not a declared property or not nullable, this test might need adjustment
            // For now, assuming it can be empty or null for a "new" user.
            // If User model enforces non-empty password_hash, this test is less relevant.
        }


        $this->assertFalse(
            $user->verifyPassword('anyPassword'),
            "verifyPassword should return false if the user's stored hash is empty."
        );
    }
}
