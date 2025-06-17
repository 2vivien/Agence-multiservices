<?php

namespace Tests\Feature;

use Tests\FeatureTestCase;
use App\Models\User;
use App\Models\Operation;
use App\Models\Service;
use App\Models\OperationType;
// use PDO; // If direct DB interaction needed

class OperationControllerTest extends FeatureTestCase
{
    // protected static ?PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // self::$pdo = \App\Models\Model::db();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Truncate relevant tables: operations, users, services, operation_types
        // For now, manual cleanup or specific test DB setup is assumed.
        // if (self::$pdo) {
        //     self::$pdo->exec("DELETE FROM operations");
        //     self::$pdo->exec("DELETE FROM users"); // Careful if users are needed across tests or created by other means
        //     self::$pdo->exec("DELETE FROM services");
        //     self::$pdo->exec("DELETE FROM operation_types");
        // }
        if (session_status() == PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = []; // Clear session
    }

    protected function loginAsGerant(array $userData = []): User
    {
        // Helper to create and "log in" a gerant user
        $defaultGerant = [
            'username' => $userData['username'] ?? 'testgerant' . uniqid(),
            'full_name' => $userData['full_name'] ?? 'Test Gerant',
            'role' => 'gerant',
            'is_active' => true,
        ];
        $gerant = User::createUser($defaultGerant, $userData['password'] ?? 'password123');
        $this->assertNotNull($gerant, "Failed to create gerant for login helper.");

        // Simulate session for this user
        $_SESSION['user_id'] = $gerant->id;
        $_SESSION['username'] = $gerant->username;
        $_SESSION['user_role'] = 'gerant';

        return $gerant;
    }

    protected function createTestService(): Service
    {
        $serviceData = ['name' => 'Test Service ' . uniqid(), 'is_active' => true, 'default_commission_rate' => 1.0];
        $service = Service::createService($serviceData);
        $this->assertNotNull($service, "Failed to create test service.");
        return $service;
    }

    protected function createTestOperationType(): OperationType
    {
        // Assuming OperationType model has a simple create method or direct DB insert for tests
        // For now, let's assume direct creation or that it exists.
        // This might need a OperationType::createOperationType() method.
        // $opType = new OperationType(); $opType->name = 'Test Type '.uniqid(); $opType->is_active = true; $opType->save();
        // return $opType;
        // For now, returning a placeholder if creation is complex without a model method
        $stmt = \App\Models\Model::db()->prepare("INSERT INTO operation_types (name, is_active) VALUES (:name, TRUE) ON CONFLICT (name) DO NOTHING RETURNING id");
        $stmt->execute(['name' => 'Test Type '.uniqid()]);
        $id = $stmt->fetchColumn();
        if (!$id) { // If ON CONFLICT and it already existed, try to find it
            $stmtFind = \App\Models\Model::db()->prepare("SELECT id FROM operation_types WHERE name LIKE 'Test Type %' LIMIT 1");
            $stmtFind->execute();
            $id = $stmtFind->fetchColumn();
        }
        return OperationType::find($id);
    }


    public function test_gerant_can_create_operation()
    {
        // $gerant = $this->loginAsGerant();
        // $service = $this->createTestService();
        // $operationType = $this->createTestOperationType();

        // $operationData = [
        //     'service_id' => $service->id,
        //     'operation_type_id' => $operationType->id,
        //     'amount' => 1000,
        //     'description' => 'Test operation creation',
        //     'operation_time' => date('Y-m-d H:i:s'),
        // ];

        // $response = $this->simulateRequest('POST', '/api/operations', $operationData);

        // $this->assertEquals(201, $response['status']); // Or 200 if your API returns 200 on create
        // $this->assertArrayHasKey('id', $response['json_body']);
        // $this->assertEquals($operationData['amount'], $response['json_body']['amount']);
        // $this->assertEquals($gerant->id, $response['json_body']['user_id']);

        // Assert operation exists in database (requires DB connection and query capability)
        // $createdOp = Operation::findById($response['json_body']['id']);
        // $this->assertNotNull($createdOp);
        // $this->assertEquals($operationData['description'], $createdOp->description);

        $this->markTestIncomplete('Operation creation test requires HTTP simulation, test DB, and potentially model setup methods.');
    }

    public function test_gerant_cannot_create_operation_with_invalid_data()
    {
        // $this->loginAsGerant();
        // $invalidData = [ /* e.g., missing amount or invalid service_id */
        //    'service_id' => 99999, // Non-existent
        //    'operation_type_id' => 99999, // Non-existent
        //    // 'amount' => null, // Missing amount
        // ];

        // $response = $this->simulateRequest('POST', '/api/operations', $invalidData);

        // $this->assertEquals(422, $response['status']); // Unprocessable Entity
        // $this->assertArrayHasKey('errors', $response['json_body']);
        // $this->assertArrayHasKey('service_id', $response['json_body']['errors']); // Example error
        // $this->assertArrayHasKey('operation_type_id', $response['json_body']['errors']);
        // $this->assertArrayHasKey('amount', $response['json_body']['errors']);

        $this->markTestIncomplete('Invalid operation data test requires HTTP simulation.');
    }

    public function test_gerant_can_list_only_own_operations()
    {
        // // Setup: Create two gérants
        // $gerantA = $this->loginAsGerant(['username' => 'gerantA']); // Logs in as gerantA
        // $gerantBData = ['username' => 'gerantB', 'full_name' => 'Gerant B', 'role' => 'gerant', 'is_active' => true];
        // $gerantB = User::createUser($gerantBData, 'passwordB');
        // $this->assertNotNull($gerantB);

        // // Create operations for Gerant A
        // Operation::createOperation([/* data for op1 for gerantA, user_id = $gerantA->id */]);
        // Operation::createOperation([/* data for op2 for gerantA, user_id = $gerantA->id */]);

        // // Create operations for Gerant B
        // Operation::createOperation([/* data for op3 for gerantB, user_id = $gerantB->id */]);

        // // Act: Fetch operations as Gerant A
        // $response = $this->simulateRequest('GET', '/api/operations');

        // // Assert: Response is 200 and contains only Gerant A's operations
        // $this->assertEquals(200, $response['status']);
        // $responseData = $response['json_body']; // Assuming it's an array of operations
        // $this->assertCount(2, $responseData); // Only 2 operations for Gerant A
        // foreach ($responseData as $op) {
        //     $this->assertEquals($gerantA->id, $op['user_id']);
        // }

        $this->markTestIncomplete('List own operations test requires HTTP simulation, test DB, and model setup.');
    }
}
