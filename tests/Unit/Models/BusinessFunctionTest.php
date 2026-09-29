<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Models\BusinessFunction;
use WaysNX\BusinessFramework\Contracts\BusinessFunctionInterface;
use WaysNX\BusinessFramework\Core\BusinessFunctionAbstract;
use JsonSerializable;

require_once __DIR__ . '/ConcreteBusinessFunction.php';

/**
 * BusinessFunctionTest
 *
 * Tests the Laravel-specific BusinessFunction implementation.
 *
 * Verifies:
 * - Laravel BusinessFunction extends framework-independent BusinessFunctionAbstract
 * - Laravel BusinessFunction implements contracts correctly
 * - JSON serialization works
 * - DateTimeImmutable timestamps work
 * - Laravel integration is seamless
 * - Backward compatibility with BaseModel
 *
 * @covers \WaysNX\BusinessFramework\Models\BusinessFunction
 * @package WaysNX\BusinessFramework\Tests\Unit\Models
 */
class BusinessFunctionTest extends TestCase
{
    /**
     * Create a test business function instance
     *
     * @return ConcreteBusinessFunction
     */
    private function createTestFunction(): ConcreteBusinessFunction
    {
        $func = new ConcreteBusinessFunction();
        $reflection = new \ReflectionClass($func);

        // Set Identity
        $reflection->getProperty('functionId')->setValue($func, 'HR.LEAVE.APPLY.APPLY_LEAVE');
        $reflection->getProperty('functionName')->setValue($func, 'Apply Leave');
        $reflection->getProperty('functionVersion')->setValue($func, '1.0.0');
        $reflection->getProperty('module')->setValue($func, 'HR');
        $reflection->getProperty('domain')->setValue($func, 'LEAVE');
        $reflection->getProperty('capability')->setValue($func, 'APPLY');
        $reflection->getProperty('lifecycleStatus')->setValue($func, 'Released');

        // Set Metadata
        $reflection->getProperty('description')->setValue($func, 'Submit an employee leave request');
        $reflection->getProperty('businessOwner')->setValue($func, 'HR Manager');
        $reflection->getProperty('technicalOwner')->setValue($func, 'HR Team');
        $reflection->getProperty('tags')->setValue($func, ['leave', 'employee', 'request']);
        $reflection->getProperty('classification')->setValue($func, 'Core');
        $reflection->getProperty('visibility')->setValue($func, 'Internal');
        $reflection->getProperty('dependencies')->setValue($func, []);
        $reflection->getProperty('eventsPublished')->setValue($func, ['LeaveRequested']);
        $reflection->getProperty('eventsConsumed')->setValue($func, []);
        $reflection->getProperty('keywords')->setValue($func, ['leave', 'absence']);

        // Set Contracts
        $reflection->getProperty('requestContract')->setValue($func, [
            'required' => ['employeeId', 'leaveType', 'startDate', 'endDate'],
            'optional' => ['reason'],
            'fields' => [
                'employeeId' => ['type' => 'string', 'description' => 'Employee ID'],
                'leaveType' => ['type' => 'string', 'description' => 'Type of leave'],
                'startDate' => ['type' => 'date', 'description' => 'Start date'],
                'endDate' => ['type' => 'date', 'description' => 'End date'],
                'reason' => ['type' => 'string', 'description' => 'Reason (optional)'],
            ],
        ]);

        $reflection->getProperty('responseContract')->setValue($func, [
            'fields' => [
                'leaveRequestId' => ['type' => 'string', 'description' => 'Request ID'],
                'status' => ['type' => 'string', 'description' => 'Request status'],
                'remainingBalance' => ['type' => 'integer', 'description' => 'Remaining leave balance'],
            ],
            'success_fields' => ['leaveRequestId', 'status', 'remainingBalance'],
        ]);

        $reflection->getProperty('errorCategories')->setValue($func, ['ValidationError', 'BusinessRuleViolation']);

        // Set Processing
        $reflection->getProperty('validationRules')->setValue($func, [
            'employeeId' => 'required|string',
            'leaveType' => 'required|string',
        ]);

        $reflection->getProperty('authorizationRequirements')->setValue($func, [
            'roles' => ['Employee', 'Manager'],
        ]);

        $reflection->getProperty('businessRules')->setValue($func, [
            'Employee must be active',
        ]);

        // Set Operational
        $reflection->getProperty('observabilityRequirements')->setValue($func, ['correlation' => true]);
        $reflection->getProperty('auditRequirements')->setValue($func, ['enabled' => true]);

        // Initialize via reflection
        $initMethod = $reflection->getMethod('initializeBusinessFunction');
        $initMethod->invoke($func);

        return $func;
    }

    // ========================================
    // INHERITANCE TESTS
    // ========================================

    /**
     * Test 1: Laravel BusinessFunction extends framework-independent BusinessFunctionAbstract
     *
     * @test
     */
    public function testBusinessFunctionExtendsAbstract(): void
    {
        $func = $this->createTestFunction();
        $this->assertInstanceOf(BusinessFunctionAbstract::class, $func);
    }

    /**
     * Test 2: Laravel BusinessFunction implements framework-independent interface
     *
     * @test
     */
    public function testBusinessFunctionImplementsInterface(): void
    {
        $func = $this->createTestFunction();
        $this->assertInstanceOf(\WaysNX\BusinessFramework\Types\BusinessFunctionInterface::class, $func);
    }

    /**
     * Test 3: Laravel BusinessFunction implements Laravel contract interface
     *
     * @test
     */
    public function testBusinessFunctionImplementsLaravelContract(): void
    {
        $func = $this->createTestFunction();
        $this->assertInstanceOf(BusinessFunctionInterface::class, $func);
    }

    /**
     * Test 4: Laravel BusinessFunction implements JsonSerializable
     *
     * @test
     */
    public function testBusinessFunctionImplementsJsonSerializable(): void
    {
        $func = $this->createTestFunction();
        $this->assertInstanceOf(JsonSerializable::class, $func);
    }

    // ========================================
    // IDENTITY TESTS
    // ========================================

    /**
     * Test 5: Laravel BusinessFunction provides function ID
     *
     * @test
     */
    public function testFunctionIdIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $this->assertEquals('HR.LEAVE.APPLY.APPLY_LEAVE', $func->getFunctionId());
    }

    /**
     * Test 6: Laravel BusinessFunction provides function name
     *
     * @test
     */
    public function testFunctionNameIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $this->assertEquals('Apply Leave', $func->getFunctionName());
    }

    /**
     * Test 7: Laravel BusinessFunction provides function version
     *
     * @test
     */
    public function testFunctionVersionIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $this->assertEquals('1.0.0', $func->getFunctionVersion());
    }

    /**
     * Test 8: Laravel BusinessFunction provides module/domain/capability
     *
     * @test
     */
    public function testModuleDomainCapabilityAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $this->assertEquals('HR', $func->getModule());
        $this->assertEquals('LEAVE', $func->getDomain());
        $this->assertEquals('APPLY', $func->getCapability());
    }

    /**
     * Test 9: Laravel BusinessFunction provides lifecycle status
     *
     * @test
     */
    public function testLifecycleStatusIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $this->assertEquals('Released', $func->getLifecycleStatus());
    }

    // ========================================
    // METADATA TESTS
    // ========================================

    /**
     * Test 10: Laravel BusinessFunction provides metadata
     *
     * @test
     */
    public function testMetadataIsAccessible(): void
    {
        $func = $this->createTestFunction();

        $this->assertEquals('Submit an employee leave request', $func->getDescription());
        $this->assertEquals('HR Manager', $func->getBusinessOwner());
        $this->assertEquals('HR Team', $func->getTechnicalOwner());
        $this->assertEquals('Core', $func->getClassification());
        $this->assertEquals('Internal', $func->getVisibility());
    }

    /**
     * Test 11: Laravel BusinessFunction provides tags
     *
     * @test
     */
    public function testTagsAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $tags = $func->getTags();

        $this->assertIsArray($tags);
        $this->assertContains('leave', $tags);
    }

    /**
     * Test 12: Laravel BusinessFunction provides events
     *
     * @test
     */
    public function testEventsAreAccessible(): void
    {
        $func = $this->createTestFunction();

        $this->assertContains('LeaveRequested', $func->getEventsPublished());
        $this->assertIsArray($func->getEventsConsumed());
    }

    // ========================================
    // CONTRACT TESTS
    // ========================================

    /**
     * Test 13: Laravel BusinessFunction provides request contract
     *
     * @test
     */
    public function testRequestContractIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $request = $func->getRequestContract();

        $this->assertIsArray($request);
        $this->assertArrayHasKey('required', $request);
        $this->assertContains('employeeId', $request['required']);
    }

    /**
     * Test 14: Laravel BusinessFunction provides response contract
     *
     * @test
     */
    public function testResponseContractIsAccessible(): void
    {
        $func = $this->createTestFunction();
        $response = $func->getResponseContract();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('fields', $response);
    }

    /**
     * Test 15: Laravel BusinessFunction provides error categories
     *
     * @test
     */
    public function testErrorCategoriesAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $errors = $func->getErrorCategories();

        $this->assertIsArray($errors);
        $this->assertContains('ValidationError', $errors);
    }

    // ========================================
    // PROCESSING DEFINITION TESTS
    // ========================================

    /**
     * Test 16: Laravel BusinessFunction provides validation rules
     *
     * @test
     */
    public function testValidationRulesAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $rules = $func->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('employeeId', $rules);
    }

    /**
     * Test 17: Laravel BusinessFunction provides authorization requirements
     *
     * @test
     */
    public function testAuthorizationRequirementsAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $auth = $func->getAuthorizationRequirements();

        $this->assertIsArray($auth);
        $this->assertArrayHasKey('roles', $auth);
    }

    /**
     * Test 18: Laravel BusinessFunction provides business rules
     *
     * @test
     */
    public function testBusinessRulesAreAccessible(): void
    {
        $func = $this->createTestFunction();
        $rules = $func->getBusinessRules();

        $this->assertIsArray($rules);
        $this->assertContains('Employee must be active', $rules);
    }

    // ========================================
    // SERIALIZATION TESTS
    // ========================================

    /**
     * Test 19: Laravel BusinessFunction can serialize to array
     *
     * @test
     */
    public function testBusinessFunctionCanSerializeToArray(): void
    {
        $func = $this->createTestFunction();
        $array = $func->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('identity', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('contract', $array);
        $this->assertArrayHasKey('processing', $array);
    }

    /**
     * Test 20: Laravel BusinessFunction can serialize to JSON
     *
     * @test
     */
    public function testBusinessFunctionCanSerializeToJson(): void
    {
        $func = $this->createTestFunction();
        $json = $func->toJson();

        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('HR.LEAVE.APPLY.APPLY_LEAVE', $decoded['identity']['functionId']);
    }

    /**
     * Test 21: Laravel BusinessFunction implements JsonSerializable correctly
     *
     * @test
     */
    public function testBusinessFunctionJsonSerializeWorks(): void
    {
        $func = $this->createTestFunction();
        $serialized = $func->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('identity', $serialized);

        // Should be JSON encodable
        $json = json_encode($serialized, \JSON_THROW_ON_ERROR);
        $this->assertIsString($json);
    }

    // ========================================
    // BASEMODEL INTEGRATION TESTS
    // ========================================

    /**
     * Test 22: Laravel BusinessFunction inherits entity ID from BaseModel
     *
     * @test
     */
    public function testBusinessFunctionHasEntityId(): void
    {
        $func = $this->createTestFunction();
        $entityId = $func->getEntityId();

        $this->assertIsString($entityId);
        $this->assertNotEmpty($entityId);
    }

    /**
     * Test 23: Laravel BusinessFunction inherits entity type from BaseModel
     *
     * @test
     */
    public function testBusinessFunctionHasEntityType(): void
    {
        $func = $this->createTestFunction();
        $entityType = $func->getEntityType();

        $this->assertIsString($entityType);
        $this->assertStringContainsString('ConcreteBusinessFunction', $entityType);
    }

    /**
     * Test 24: Laravel BusinessFunction inherits versioning from BaseModel
     *
     * @test
     */
    public function testBusinessFunctionHasVersioning(): void
    {
        $func = $this->createTestFunction();
        $version = $func->getEntityVersion();

        $this->assertIsInt($version);
        $this->assertEquals(1, $version);
    }

    /**
     * Test 25: Laravel BusinessFunction has audit timestamps
     *
     * @test
     */
    public function testBusinessFunctionHasAuditTimestamps(): void
    {
        $func = $this->createTestFunction();

        $this->assertInstanceOf(\DateTimeImmutable::class, $func->getCreatedAt());
        $this->assertNull($func->getUpdatedAt());
        $this->assertNull($func->getDeletedAt());
    }

    // ========================================
    // STRING REPRESENTATION TEST
    // ========================================

    /**
     * Test 26: Laravel BusinessFunction has string representation
     *
     * @test
     */
    public function testBusinessFunctionHasStringRepresentation(): void
    {
        $func = $this->createTestFunction();
        $string = (string)$func;

        $this->assertIsString($string);
        $this->assertStringContainsString('BusinessFunction', $string);
        $this->assertStringContainsString('HR.LEAVE.APPLY.APPLY_LEAVE', $string);
        $this->assertStringContainsString('Apply Leave', $string);
        $this->assertStringContainsString('1.0.0', $string);
    }

    // ========================================
    // COMPLETE CONTRACT DEFINITION TEST
    // ========================================

    /**
     * Test 27: Laravel BusinessFunction provides complete contract definition
     *
     * @test
     */
    public function testCompleteContractDefinitionIsComprehensive(): void
    {
        $func = $this->createTestFunction();
        $definition = $func->getCompleteContractDefinition();

        // Verify all major sections
        $this->assertArrayHasKey('identity', $definition);
        $this->assertArrayHasKey('metadata', $definition);
        $this->assertArrayHasKey('contract', $definition);
        $this->assertArrayHasKey('processing', $definition);
        $this->assertArrayHasKey('operational', $definition);

        // Verify identity section is complete
        $identity = $definition['identity'];
        $this->assertEquals('HR.LEAVE.APPLY.APPLY_LEAVE', $identity['functionId']);
        $this->assertEquals('Apply Leave', $identity['functionName']);
        $this->assertEquals('1.0.0', $identity['functionVersion']);
        $this->assertEquals('HR', $identity['module']);
        $this->assertEquals('LEAVE', $identity['domain']);
        $this->assertEquals('APPLY', $identity['capability']);
        $this->assertEquals('Released', $identity['lifecycleStatus']);

        // Verify metadata section contains owners
        $metadata = $definition['metadata'];
        $this->assertEquals('HR Manager', $metadata['businessOwner']);
        $this->assertEquals('HR Team', $metadata['technicalOwner']);

        // Verify contract section
        $contract = $definition['contract'];
        $this->assertArrayHasKey('request', $contract);
        $this->assertArrayHasKey('response', $contract);
        $this->assertArrayHasKey('errors', $contract);
    }
}
