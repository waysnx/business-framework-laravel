<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Models\BusinessCapability;

/**
 * BusinessCapabilityTest
 *
 * Comprehensive test suite for BusinessCapability model implementation.
 *
 * Tests verify:
 * - Capability creation and initialization
 * - Identity fields (ID, name, description)
 * - Domain relationship
 * - Business owner/ownership
 * - Lifecycle and status
 * - Business outcome
 * - KPIs
 * - Dependencies
 * - Workflows
 * - Services
 * - Business rules
 * - Policies
 * - Events
 * - Serialization (toArray, toJson)
 * - Validation
 * - Version consistency
 * - Framework independence
 * - Domain hierarchy integrity
 * - Existing Domain behavior preservation
 *
 * @covers \WaysNX\BusinessFramework\Models\BusinessCapability
 * @covers \WaysNX\BusinessFramework\Core\BusinessCapabilityAbstract
 */
class BusinessCapabilityTest extends TestCase
{
    /**
     * Create a valid BusinessCapability instance for testing
     *
     * @return BusinessCapability
     */
    private function createValidCapability(): BusinessCapability
    {
        $capability = new BusinessCapability();

        $reflection = new \ReflectionClass($capability);

        // Set via reflection to bypass setter validation during setup
        $reflection->getProperty('capabilityId')->setValue($capability, 'emp-search');
        $reflection->getProperty('capabilityName')->setValue($capability, 'Employee Search');
        $reflection->getProperty('description')->setValue($capability, 'Search employee records');
        $reflection->getProperty('domainId')->setValue($capability, 'EMP_MGMT');
        $reflection->getProperty('businessOwner')->setValue($capability, 'hr-manager-001');
        $reflection->getProperty('status')->setValue($capability, BusinessCapability::IMPLEMENT);
        $reflection->getProperty('businessOutcome')->setValue($capability, 'Locate employee info');

        // Initialize via reflection to avoid visibility issues
        $initMethod = $reflection->getMethod('initializeCapability');
        $initMethod->invoke($capability);

        return $capability;
    }

    /**
     * @test
     * @group capability-creation
     */
    public function testCanCreateValidCapability(): void
    {
        $capability = $this->createValidCapability();

        $this->assertInstanceOf(BusinessCapability::class, $capability);
        $this->assertEquals('emp-search', $capability->getCapabilityId());
        $this->assertEquals('Employee Search', $capability->getCapabilityName());
    }

    /**
     * @test
     * @group capability-creation
     */
    public function testCreationInitializesBaseModelFields(): void
    {
        $capability = $this->createValidCapability();

        $this->assertNotEmpty($capability->getEntityId());
        $this->assertEquals('Capability', $capability->getEntityType());
        $this->assertEquals(1, $capability->getEntityVersion());
        $this->assertNotNull($capability->getCreatedAt());
    }

    // ========================================
    // IDENTITY FIELDS TESTS
    // ========================================

    /**
     * @test
     * @group capability-identity
     */
    public function testCapabilityIdGetterSetter(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('emp-search', $capability->getCapabilityId());
    }

    /**
     * @test
     * @group capability-identity
     */
    public function testCapabilityIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capability ID cannot be empty');

        $capability = $this->createValidCapability();
        $capability->setCapabilityId('');
    }

    /**
     * @test
     * @group capability-identity
     */
    public function testCapabilityNameGetterSetter(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('Employee Search', $capability->getCapabilityName());
    }

    /**
     * @test
     * @group capability-identity
     */
    public function testCapabilityNameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capability name cannot be empty');

        $capability = $this->createValidCapability();
        $capability->setCapabilityName('');
    }

    /**
     * @test
     * @group capability-identity
     */
    public function testDescriptionGetterSetter(): void
    {
        $capability = $this->createValidCapability();
        $capability->setDescription('Search and filter employee records');

        $this->assertEquals('Search and filter employee records', $capability->getDescription());
    }

    /**
     * @test
     * @group capability-identity
     */
    public function testDescriptionCanBeEmpty(): void
    {
        $capability = $this->createValidCapability();
        $capability->setDescription('');

        $this->assertEquals('', $capability->getDescription());
    }

    // ========================================
    // DOMAIN RELATIONSHIP TESTS
    // ========================================

    /**
     * @test
     * @group capability-domain
     */
    public function testDomainIdGetterSetter(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('EMP_MGMT', $capability->getDomainId());
    }

    /**
     * @test
     * @group capability-domain
     */
    public function testDomainIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain ID cannot be empty');

        $capability = $this->createValidCapability();
        $capability->setDomainId('');
    }

    /**
     * @test
     * @group capability-domain
     */
    public function testDomainIdCanBeInteger(): void
    {
        $capability = $this->createValidCapability();
        $capability->setDomainId(123);

        $this->assertEquals(123, $capability->getDomainId());
    }

    // ========================================
    // BUSINESS OWNER TESTS
    // ========================================

    /**
     * @test
     * @group capability-owner
     */
    public function testBusinessOwnerGetterSetter(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('hr-manager-001', $capability->getBusinessOwner());
    }

    /**
     * @test
     * @group capability-owner
     */
    public function testBusinessOwnerCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business owner cannot be empty');

        $capability = $this->createValidCapability();
        $capability->setBusinessOwner('');
    }

    /**
     * @test
     * @group capability-owner
     */
    public function testBusinessOwnerCanBeInteger(): void
    {
        $capability = $this->createValidCapability();
        $capability->setBusinessOwner(123);

        $this->assertEquals(123, $capability->getBusinessOwner());
    }

    // ========================================
    // LIFECYCLE/STATUS TESTS
    // ========================================

    /**
     * @test
     * @group capability-lifecycle
     */
    public function testAllValidLifecycleStates(): void
    {
        $states = [
            BusinessCapability::IDENTIFY,
            BusinessCapability::ANALYZE,
            BusinessCapability::DESIGN,
            BusinessCapability::REVIEW,
            BusinessCapability::APPROVE,
            BusinessCapability::IMPLEMENT,
            BusinessCapability::OPERATE,
            BusinessCapability::IMPROVE,
            BusinessCapability::RETIRE,
        ];

        foreach ($states as $state) {
            $capability = $this->createValidCapability();
            $capability->setStatus($state);
            $this->assertEquals($state, $capability->getStatus());
        }
    }

    /**
     * @test
     * @group capability-lifecycle
     */
    public function testInvalidStatusRaisesException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid capability status');

        $capability = $this->createValidCapability();
        $capability->setStatus('InvalidStatus');
    }

    /**
     * @test
     * @group capability-lifecycle
     */
    public function testDefaultStatusIsIdentify(): void
    {
        $capability = new BusinessCapability();
        $this->assertEquals(BusinessCapability::IDENTIFY, $capability->getStatus());
    }

    // ========================================
    // BUSINESS OUTCOME TESTS
    // ========================================

    /**
     * @test
     * @group capability-outcome
     */
    public function testBusinessOutcomeGetterSetter(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('Locate employee info', $capability->getBusinessOutcome());
    }

    /**
     * @test
     * @group capability-outcome
     */
    public function testBusinessOutcomeCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business outcome cannot be empty');

        $capability = $this->createValidCapability();
        $capability->setBusinessOutcome('');
    }

    /**
     * @test
     * @group capability-outcome
     */
    public function testBusinessOutcomeMetadataPreserved(): void
    {
        $capability = $this->createValidCapability();
        $outcome = 'Enable rapid employee search across organization';
        $capability->setBusinessOutcome($outcome);

        $this->assertEquals($outcome, $capability->getBusinessOutcome());
    }

    // ========================================
    // WORKFLOWS TESTS
    // ========================================

    /**
     * @test
     * @group capability-workflows
     */
    public function testAddWorkflowWithValidStructure(): void
    {
        $capability = $this->createValidCapability();
        $workflow = ['id' => 'wf-search', 'name' => 'Search Workflow'];
        $capability->addWorkflow($workflow);

        $this->assertTrue($capability->hasWorkflow('wf-search'));
        $this->assertEquals($workflow, $capability->getWorkflow('wf-search'));
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testCannotAddDuplicateWorkflow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow with id "wf-search" already exists');

        $capability = $this->createValidCapability();
        $capability->addWorkflow(['id' => 'wf-search', 'name' => 'Search Workflow']);
        $capability->addWorkflow(['id' => 'wf-search', 'name' => 'Duplicate Workflow']);
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testRemoveWorkflowById(): void
    {
        $capability = $this->createValidCapability();
        $capability->addWorkflow(['id' => 'wf-search', 'name' => 'Search Workflow']);
        $capability->removeWorkflow('wf-search');

        $this->assertFalse($capability->hasWorkflow('wf-search'));
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testGetWorkflowReturnsCorrectWorkflow(): void
    {
        $capability = $this->createValidCapability();
        $workflow1 = ['id' => 'wf-search', 'name' => 'Search Workflow'];
        $workflow2 = ['id' => 'wf-filter', 'name' => 'Filter Workflow'];
        $capability->addWorkflow($workflow1);
        $capability->addWorkflow($workflow2);

        $this->assertEquals($workflow1, $capability->getWorkflow('wf-search'));
        $this->assertEquals($workflow2, $capability->getWorkflow('wf-filter'));
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testHasWorkflowChecksCorrectly(): void
    {
        $capability = $this->createValidCapability();
        $capability->addWorkflow(['id' => 'wf-search', 'name' => 'Search Workflow']);

        $this->assertTrue($capability->hasWorkflow('wf-search'));
        $this->assertFalse($capability->hasWorkflow('wf-nonexistent'));
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testWorkflowMustHaveIdField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow must have an id field');

        $capability = $this->createValidCapability();
        $capability->addWorkflow(['name' => 'Workflow without ID']);
    }

    /**
     * @test
     * @group capability-workflows
     */
    public function testMultipleWorkflowsSupported(): void
    {
        $capability = $this->createValidCapability();
        $capability->addWorkflow(['id' => 'wf-1', 'name' => 'Workflow 1']);
        $capability->addWorkflow(['id' => 'wf-2', 'name' => 'Workflow 2']);
        $capability->addWorkflow(['id' => 'wf-3', 'name' => 'Workflow 3']);

        $workflows = $capability->getWorkflows();
        $this->assertCount(3, $workflows);
    }

    // ========================================
    // SERVICES TESTS
    // ========================================

    /**
     * @test
     * @group capability-services
     */
    public function testAddServiceWithValidStructure(): void
    {
        $capability = $this->createValidCapability();
        $service = ['id' => 'svc-search', 'name' => 'Employee Search Service'];
        $capability->addService($service);

        $this->assertTrue($capability->hasService('svc-search'));
        $this->assertEquals($service, $capability->getService('svc-search'));
    }

    /**
     * @test
     * @group capability-services
     */
    public function testCannotAddDuplicateService(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service with id "svc-search" already exists');

        $capability = $this->createValidCapability();
        $capability->addService(['id' => 'svc-search', 'name' => 'Employee Search Service']);
        $capability->addService(['id' => 'svc-search', 'name' => 'Duplicate Service']);
    }

    /**
     * @test
     * @group capability-services
     */
    public function testRemoveServiceById(): void
    {
        $capability = $this->createValidCapability();
        $capability->addService(['id' => 'svc-search', 'name' => 'Employee Search Service']);
        $capability->removeService('svc-search');

        $this->assertFalse($capability->hasService('svc-search'));
    }

    /**
     * @test
     * @group capability-services
     */
    public function testGetServiceReturnsCorrectService(): void
    {
        $capability = $this->createValidCapability();
        $service1 = ['id' => 'svc-search', 'name' => 'Search Service'];
        $service2 = ['id' => 'svc-create', 'name' => 'Create Service'];
        $capability->addService($service1);
        $capability->addService($service2);

        $this->assertEquals($service1, $capability->getService('svc-search'));
        $this->assertEquals($service2, $capability->getService('svc-create'));
    }

    /**
     * @test
     * @group capability-services
     */
    public function testHasServiceChecksCorrectly(): void
    {
        $capability = $this->createValidCapability();
        $capability->addService(['id' => 'svc-search', 'name' => 'Employee Search Service']);

        $this->assertTrue($capability->hasService('svc-search'));
        $this->assertFalse($capability->hasService('svc-nonexistent'));
    }

    /**
     * @test
     * @group capability-services
     */
    public function testServiceMustHaveIdField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service must have an id field');

        $capability = $this->createValidCapability();
        $capability->addService(['name' => 'Service without ID']);
    }

    /**
     * @test
     * @group capability-services
     */
    public function testMultipleServicesSupported(): void
    {
        $capability = $this->createValidCapability();
        $capability->addService(['id' => 'svc-1', 'name' => 'Service 1']);
        $capability->addService(['id' => 'svc-2', 'name' => 'Service 2']);
        $capability->addService(['id' => 'svc-3', 'name' => 'Service 3']);

        $services = $capability->getServices();
        $this->assertCount(3, $services);
    }

    // ========================================
    // KPIs TESTS
    // ========================================

    /**
     * @test
     * @group capability-kpis
     */
    public function testKpisGetterSetter(): void
    {
        $capability = $this->createValidCapability();
        $kpis = [
            ['name' => 'Search Efficiency', 'target' => 95],
            ['name' => 'Response Time', 'target' => 100],
        ];
        $capability->setKpis($kpis);

        $this->assertEquals($kpis, $capability->getKpis());
    }

    /**
     * @test
     * @group capability-kpis
     */
    public function testKpisCanBeEmptyArray(): void
    {
        $capability = $this->createValidCapability();
        $capability->setKpis([]);

        $this->assertEmpty($capability->getKpis());
    }

    /**
     * @test
     * @group capability-kpis
     */
    public function testKpiMetadataPreserved(): void
    {
        $capability = $this->createValidCapability();
        $kpis = [
            ['name' => 'Efficiency', 'target' => 95, 'unit' => 'percent', 'owner' => 'manager'],
        ];
        $capability->setKpis($kpis);

        $this->assertEquals($kpis, $capability->getKpis());
    }

    // ========================================
    // DEPENDENCIES TESTS
    // ========================================

    /**
     * @test
     * @group capability-dependencies
     */
    public function testAddDependency(): void
    {
        $capability = $this->createValidCapability();
        $capability->addDependency('emp-profile');

        $this->assertTrue($capability->hasDependency('emp-profile'));
    }

    /**
     * @test
     * @group capability-dependencies
     */
    public function testCannotAddDuplicateDependency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capability dependency "emp-profile" already exists');

        $capability = $this->createValidCapability();
        $capability->addDependency('emp-profile');
        $capability->addDependency('emp-profile');
    }

    /**
     * @test
     * @group capability-dependencies
     */
    public function testRemoveDependency(): void
    {
        $capability = $this->createValidCapability();
        $capability->addDependency('emp-profile');
        $capability->removeDependency('emp-profile');

        $this->assertFalse($capability->hasDependency('emp-profile'));
    }

    /**
     * @test
     * @group capability-dependencies
     */
    public function testHasDependencyChecksCorrectly(): void
    {
        $capability = $this->createValidCapability();
        $capability->addDependency('emp-profile');

        $this->assertTrue($capability->hasDependency('emp-profile'));
        $this->assertFalse($capability->hasDependency('emp-nonexistent'));
    }

    /**
     * @test
     * @group capability-dependencies
     */
    public function testGetDependenciesReturnsAll(): void
    {
        $capability = $this->createValidCapability();
        $capability->addDependency('emp-profile');
        $capability->addDependency('emp-history');
        $capability->addDependency('emp-documents');

        $dependencies = $capability->getDependencies();
        $this->assertCount(3, $dependencies);
        $this->assertContains('emp-profile', $dependencies);
    }

    // ========================================
    // BUSINESS RULES TESTS
    // ========================================

    /**
     * @test
     * @group capability-business-rules
     */
    public function testBusinessRulesGetterSetter(): void
    {
        $capability = $this->createValidCapability();
        $rules = [
            ['ruleId' => 'rule-1', 'description' => 'Rule 1'],
            ['ruleId' => 'rule-2', 'description' => 'Rule 2'],
        ];
        $capability->setBusinessRules($rules);

        $this->assertEquals($rules, $capability->getBusinessRules());
    }

    /**
     * @test
     * @group capability-business-rules
     */
    public function testCanStoreRuleDefinitions(): void
    {
        $capability = $this->createValidCapability();
        $capability->setBusinessRules([
            ['ruleId' => 'br-001', 'description' => 'Only active employees'],
            ['ruleId' => 'br-002', 'description' => 'Only managers can view salary'],
        ]);

        $rules = $capability->getBusinessRules();
        $this->assertCount(2, $rules);
    }

    // ========================================
    // POLICIES TESTS
    // ========================================

    /**
     * @test
     * @group capability-policies
     */
    public function testPoliciesGetterSetter(): void
    {
        $capability = $this->createValidCapability();
        $policies = [
            ['policyId' => 'pol-1', 'description' => 'Policy 1'],
            ['policyId' => 'pol-2', 'description' => 'Policy 2'],
        ];
        $capability->setPolicies($policies);

        $this->assertEquals($policies, $capability->getPolicies());
    }

    /**
     * @test
     * @group capability-policies
     */
    public function testCanStorePolicyDefinitions(): void
    {
        $capability = $this->createValidCapability();
        $capability->setPolicies([
            ['policyId' => 'policy-001', 'description' => 'Data retention policy'],
            ['policyId' => 'policy-002', 'description' => 'Access control policy'],
        ]);

        $policies = $capability->getPolicies();
        $this->assertCount(2, $policies);
    }

    // ========================================
    // EVENTS TESTS
    // ========================================

    /**
     * @test
     * @group capability-events
     */
    public function testEventsGetterSetter(): void
    {
        $capability = $this->createValidCapability();
        $events = [
            'published' => [['eventId' => 'evt-1', 'name' => 'EmployeeSearched']],
            'consumed' => [['eventId' => 'evt-2', 'name' => 'EmployeeUpdated']],
        ];
        $capability->setEvents($events);

        $this->assertEquals($events, $capability->getEvents());
    }

    /**
     * @test
     * @group capability-events
     */
    public function testCanStoreEventDefinitions(): void
    {
        $capability = $this->createValidCapability();
        $capability->setEvents([
            'published' => [
                ['eventId' => 'employee.searched', 'description' => 'Employee search completed'],
            ],
            'consumed' => [
                ['eventId' => 'employee.created', 'description' => 'New employee created'],
            ],
        ]);

        $events = $capability->getEvents();
        $this->assertNotEmpty($events);
    }

    // ========================================
    // SERIALIZATION TESTS
    // ========================================

    /**
     * @test
     * @group capability-serialization
     */
    public function testToArrayReturnsAllFields(): void
    {
        $capability = $this->createValidCapability();
        $capability->setKpis([['name' => 'Efficiency', 'target' => 95]]);

        $array = $capability->toArray();

        $this->assertArrayHasKey('capability_id', $array);
        $this->assertArrayHasKey('capability_name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('domain_id', $array);
        $this->assertArrayHasKey('business_owner', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('business_outcome', $array);
        $this->assertArrayHasKey('kpis', $array);
        $this->assertArrayHasKey('entity_id', $array);
        $this->assertArrayHasKey('entity_type', $array);
    }

    /**
     * @test
     * @group capability-serialization
     */
    public function testToJsonReturnsValidJsonString(): void
    {
        $capability = $this->createValidCapability();

        $json = $capability->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('emp-search', $decoded['capability_id']);
    }

    /**
     * @test
     * @group capability-serialization
     */
    public function testJsonSerializableWorks(): void
    {
        $capability = $this->createValidCapability();

        $json = json_encode($capability);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('emp-search', $decoded['capability_id']);
    }

    /**
     * @test
     * @group capability-serialization
     */
    public function testTimestampsInIso8601Format(): void
    {
        $capability = $this->createValidCapability();

        $array = $capability->toArray();

        $this->assertNotNull($array['created_at']);
        // Verify ISO 8601 format (YYYY-MM-DDTHH:MM:SS+00:00 or similar)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $array['created_at']);
    }

    // ========================================
    // VALIDATION TESTS
    // ========================================

    /**
     * @test
     * @group capability-validation
     */
    public function testValidCapabilityPassesValidation(): void
    {
        $capability = $this->createValidCapability();

        // No exceptions thrown
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group capability-validation
     */
    public function testMissingCapabilityIdFailsValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capability ID is required');

        $capability = new BusinessCapability();

        $reflection = new \ReflectionClass($capability);

        // Set all fields except capabilityId (which is empty by default)
        $reflection->getProperty('capabilityName')->setValue($capability, 'Employee Search');
        $reflection->getProperty('description')->setValue($capability, 'Search employees');
        $reflection->getProperty('domainId')->setValue($capability, 'EMP_MGMT');
        $reflection->getProperty('businessOwner')->setValue($capability, 'hr-manager-001');
        $reflection->getProperty('status')->setValue($capability, BusinessCapability::IMPLEMENT);
        $reflection->getProperty('businessOutcome')->setValue($capability, 'Find employees quickly');

        // Try to initialize - should fail because capabilityId is empty
        $initMethod = $reflection->getMethod('initializeCapability');
        $initMethod->invoke($capability);
    }

    /**
     * @test
     * @group capability-validation
     */
    public function testMissingRequiredFieldsFailValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $capability = new BusinessCapability();

        $reflection = new \ReflectionClass($capability);

        // Set only capabilityId, leave others empty
        $reflection->getProperty('capabilityId')->setValue($capability, 'emp-search');
        // capabilityName is empty by default

        // Try to initialize - should fail
        $initMethod = $reflection->getMethod('initializeCapability');
        $initMethod->invoke($capability);
    }

    // ========================================
    // VERSION CONSISTENCY TESTS
    // ========================================

    /**
     * @test
     * @group capability-version
     */
    public function testEntityVersionInheritedFromBaseModel(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals(1, $capability->getEntityVersion());
    }

    /**
     * @test
     * @group capability-version
     */
    public function testEntityTypeSetToCapability(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('Capability', $capability->getEntityType());
    }

    /**
     * @test
     * @group capability-version
     */
    public function testVersionTrackedByBaseModel(): void
    {
        $capability = $this->createValidCapability();

        $version = $capability->getEntityVersion();
        $this->assertIsInt($version);
        $this->assertGreaterThanOrEqual(1, $version);
    }

    // ========================================
    // FRAMEWORK INDEPENDENCE TESTS
    // ========================================

    /**
     * @test
     * @group capability-framework-independence
     */
    public function testNoLaravelObjectsInToArray(): void
    {
        $capability = $this->createValidCapability();

        $array = $capability->toArray();

        // Verify all values are primitives or arrays
        foreach ($array as $key => $value) {
            $this->assertTrue(
                is_string($value) || is_int($value) || is_bool($value) || is_null($value) || is_array($value),
                "Key '{$key}' contains non-primitive type: " . gettype($value)
            );
        }
    }

    /**
     * @test
     * @group capability-framework-independence
     */
    public function testSerializationProducesPureJson(): void
    {
        $capability = $this->createValidCapability();

        $json = $capability->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('emp-search', $decoded['capability_id']);
        $this->assertEquals('Employee Search', $decoded['capability_name']);
    }

    // ========================================
    // DOMAIN HIERARCHY TESTS
    // ========================================

    /**
     * @test
     * @group capability-hierarchy
     */
    public function testCapabilityRemainsInDomainHierarchy(): void
    {
        $capability = $this->createValidCapability();

        // Capability belongs to exactly one Domain
        $this->assertEquals('EMP_MGMT', $capability->getDomainId());
    }

    /**
     * @test
     * @group capability-hierarchy
     */
    public function testParentDomainReferencePreserved(): void
    {
        $capability = $this->createValidCapability();

        $this->assertEquals('EMP_MGMT', $capability->getDomainId());
    }

    // ========================================
    // STRING REPRESENTATION TESTS
    // ========================================

    /**
     * @test
     * @group capability-string
     */
    public function testStringRepresentation(): void
    {
        $capability = $this->createValidCapability();

        $str = $capability->__toString();
        $this->assertStringContainsString('Capability(emp-search', $str);
        $this->assertStringContainsString('Employee Search', $str);
    }
}
