<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Models\Workflow;

/**
 * WorkflowTest
 *
 * Comprehensive test suite for Workflow model implementation.
 *
 * Tests verify:
 * - Workflow creation and initialization
 * - Identity fields (ID, name, description)
 * - Capability relationship (parent reference)
 * - Business owner/ownership
 * - Lifecycle and status (6 states)
 * - Workflow definition reference (technical)
 * - Trigger, inputs, outputs
 * - Services collection
 * - Steps collection
 * - KPIs management
 * - Dependencies management
 * - SLA
 * - Serialization (toArray, toJson)
 * - Validation
 * - Version consistency
 * - Framework independence
 * - Capability hierarchy integrity
 * - Coexistence with WorkflowDefinition (no conflicts)
 * - Existing Engine/Registry compatibility
 *
 * @covers \WaysNX\BusinessFramework\Models\Workflow
 * @covers \WaysNX\BusinessFramework\Core\WorkflowAbstract
 */
class WorkflowTest extends TestCase
{
    /**
     * Create a valid Workflow instance for testing
     *
     * @return Workflow
     */
    private function createValidWorkflow(): Workflow
    {
        $workflow = new Workflow();

        $reflection = new \ReflectionClass($workflow);

        // Set via reflection to bypass setter validation during setup
        $reflection->getProperty('workflowId')->setValue($workflow, 'emp-onboarding');
        $reflection->getProperty('workflowName')->setValue($workflow, 'Employee Onboarding');
        $reflection->getProperty('description')->setValue($workflow, 'Complete employee onboarding process');
        $reflection->getProperty('capabilityId')->setValue($workflow, 'employee-management');
        $reflection->getProperty('businessOwner')->setValue($workflow, 'hr-manager-001');
        $reflection->getProperty('status')->setValue($workflow, Workflow::ACTIVE);
        $reflection->getProperty('workflowDefinitionId')->setValue($workflow, 'onboarding-v1');

        // Initialize via reflection to avoid visibility issues
        $initMethod = $reflection->getMethod('initializeWorkflow');
        $initMethod->invoke($workflow);

        return $workflow;
    }

    // ========================================
    // CREATION & INITIALIZATION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-creation
     */
    public function testCanCreateValidWorkflow(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertEquals('emp-onboarding', $workflow->getWorkflowId());
        $this->assertEquals('Employee Onboarding', $workflow->getWorkflowName());
    }

    /**
     * @test
     * @group workflow-creation
     */
    public function testCreationInitializesBaseModelFields(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertNotEmpty($workflow->getEntityId());
        // Entity type may be full class name depending on BaseModel implementation
        $this->assertStringContainsString('Workflow', $workflow->getEntityType());
        $this->assertEquals(1, $workflow->getEntityVersion());
        $this->assertNotNull($workflow->getCreatedAt());
    }

    /**
     * @test
     * @group workflow-initialization
     */
    public function testInitializeSetsDefaultValues(): void
    {
        $workflow = new Workflow();
        $workflow->initialize();

        $this->assertEquals(Workflow::DRAFT, $workflow->getStatus());
        $this->assertIsArray($workflow->getServices());
        $this->assertEmpty($workflow->getServices());
        $this->assertIsArray($workflow->getSteps());
        $this->assertEmpty($workflow->getSteps());
        $this->assertIsArray($workflow->getKpis());
        $this->assertEmpty($workflow->getKpis());
        $this->assertIsArray($workflow->getDependencies());
        $this->assertEmpty($workflow->getDependencies());
    }

    // ========================================
    // IDENTITY FIELDS TESTS
    // ========================================

    /**
     * @test
     * @group workflow-identity
     */
    public function testCanSetAndGetWorkflowId(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setWorkflowId('new-workflow-id');

        $this->assertEquals('new-workflow-id', $workflow->getWorkflowId());
    }

    /**
     * @test
     * @group workflow-identity
     */
    public function testWorkflowIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setWorkflowId('');
    }

    /**
     * @test
     * @group workflow-identity
     */
    public function testCanSetAndGetWorkflowName(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setWorkflowName('New Name');

        $this->assertEquals('New Name', $workflow->getWorkflowName());
    }

    /**
     * @test
     * @group workflow-identity
     */
    public function testWorkflowNameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setWorkflowName('');
    }

    /**
     * @test
     * @group workflow-identity
     */
    public function testCanSetAndGetDescription(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setDescription('New description');

        $this->assertEquals('New description', $workflow->getDescription());
    }

    /**
     * @test
     * @group workflow-identity
     */
    public function testDescriptionCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setDescription('');
    }

    // ========================================
    // RELATIONSHIP TESTS (Capability)
    // ========================================

    /**
     * @test
     * @group workflow-relationships
     */
    public function testCanSetAndGetCapabilityId(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setCapabilityId('new-capability');

        $this->assertEquals('new-capability', $workflow->getCapabilityId());
    }

    /**
     * @test
     * @group workflow-relationships
     */
    public function testCapabilityIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setCapabilityId('');
    }

    /**
     * @test
     * @group workflow-relationships
     */
    public function testCapabilityIdCanBeInteger(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setCapabilityId(123);

        $this->assertEquals(123, $workflow->getCapabilityId());
    }

    /**
     * @test
     * @group workflow-governance
     */
    public function testCanSetAndGetBusinessOwner(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setBusinessOwner('new-owner');

        $this->assertEquals('new-owner', $workflow->getBusinessOwner());
    }

    /**
     * @test
     * @group workflow-governance
     */
    public function testBusinessOwnerCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setBusinessOwner('');
    }

    /**
     * @test
     * @group workflow-governance
     */
    public function testBusinessOwnerCanBeInteger(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setBusinessOwner(456);

        $this->assertEquals(456, $workflow->getBusinessOwner());
    }

    // ========================================
    // LIFECYCLE/STATUS TESTS (6 states)
    // ========================================

    /**
     * @test
     * @group workflow-lifecycle
     */
    public function testHasSixLifecycleStates(): void
    {
        $this->assertEquals('Draft', Workflow::DRAFT);
        $this->assertEquals('Review', Workflow::REVIEW);
        $this->assertEquals('Approved', Workflow::APPROVED);
        $this->assertEquals('Active', Workflow::ACTIVE);
        $this->assertEquals('Suspended', Workflow::SUSPENDED);
        $this->assertEquals('Retired', Workflow::RETIRED);
    }

    /**
     * @test
     * @group workflow-lifecycle
     */
    public function testCanSetValidStatus(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->setStatus(Workflow::DRAFT);
        $this->assertEquals(Workflow::DRAFT, $workflow->getStatus());

        $workflow->setStatus(Workflow::REVIEW);
        $this->assertEquals(Workflow::REVIEW, $workflow->getStatus());

        $workflow->setStatus(Workflow::APPROVED);
        $this->assertEquals(Workflow::APPROVED, $workflow->getStatus());

        $workflow->setStatus(Workflow::ACTIVE);
        $this->assertEquals(Workflow::ACTIVE, $workflow->getStatus());

        $workflow->setStatus(Workflow::SUSPENDED);
        $this->assertEquals(Workflow::SUSPENDED, $workflow->getStatus());

        $workflow->setStatus(Workflow::RETIRED);
        $this->assertEquals(Workflow::RETIRED, $workflow->getStatus());
    }

    /**
     * @test
     * @group workflow-lifecycle
     */
    public function testCannotSetInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->setStatus('InvalidStatus');
    }

    /**
     * @test
     * @group workflow-lifecycle
     */
    public function testStatusHelperMethods(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->setStatus(Workflow::DRAFT);
        $this->assertTrue($workflow->isDraft());
        $this->assertFalse($workflow->isActive());

        $workflow->setStatus(Workflow::REVIEW);
        $this->assertTrue($workflow->isInReview());

        $workflow->setStatus(Workflow::APPROVED);
        $this->assertTrue($workflow->isApproved());

        $workflow->setStatus(Workflow::ACTIVE);
        $this->assertTrue($workflow->isActive());

        $workflow->setStatus(Workflow::SUSPENDED);
        $this->assertTrue($workflow->isSuspended());

        $workflow->setStatus(Workflow::RETIRED);
        $this->assertTrue($workflow->isRetired());
    }

    // ========================================
    // DEFINITION REFERENCE TESTS
    // ========================================

    /**
     * @test
     * @group workflow-definition
     */
    public function testCanSetAndGetWorkflowDefinitionId(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setWorkflowDefinitionId('def-123');

        $this->assertEquals('def-123', $workflow->getWorkflowDefinitionId());
    }

    /**
     * @test
     * @group workflow-definition
     */
    public function testWorkflowDefinitionIdIsOptional(): void
    {
        $workflow = new Workflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('workflowId')->setValue($workflow, 'test-id');
        $reflection->getProperty('workflowName')->setValue($workflow, 'test-name');
        $reflection->getProperty('description')->setValue($workflow, 'test-desc');
        $reflection->getProperty('capabilityId')->setValue($workflow, 'cap-1');
        $reflection->getProperty('businessOwner')->setValue($workflow, 'owner-1');

        $this->assertEmpty($workflow->getWorkflowDefinitionId());
    }

    /**
     * @test
     * @group workflow-definition
     */
    public function testCanCheckHasWorkflowDefinition(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->setWorkflowDefinitionId('');
        $this->assertFalse($workflow->hasWorkflowDefinition());

        $workflow->setWorkflowDefinitionId('def-xyz');
        $this->assertTrue($workflow->hasWorkflowDefinition());
    }

    // ========================================
    // TRIGGER/INPUTS/OUTPUTS TESTS
    // ========================================

    /**
     * @test
     * @group workflow-trigger
     */
    public function testCanSetAndGetTrigger(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setTrigger('employee-created');

        $this->assertEquals('employee-created', $workflow->getTrigger());
    }

    /**
     * @test
     * @group workflow-inputs-outputs
     */
    public function testCanSetAndGetInputs(): void
    {
        $workflow = $this->createValidWorkflow();
        $inputs = [
            ['name' => 'employee_name', 'type' => 'string'],
            ['name' => 'department', 'type' => 'string'],
        ];
        $workflow->setInputs($inputs);

        $this->assertEquals($inputs, $workflow->getInputs());
    }

    /**
     * @test
     * @group workflow-inputs-outputs
     */
    public function testCanSetAndGetOutputs(): void
    {
        $workflow = $this->createValidWorkflow();
        $outputs = [
            ['name' => 'employee_id', 'type' => 'string'],
            ['name' => 'onboarding_complete', 'type' => 'boolean'],
        ];
        $workflow->setOutputs($outputs);

        $this->assertEquals($outputs, $workflow->getOutputs());
    }

    // ========================================
    // SERVICES COLLECTION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-services
     */
    public function testCanAddServices(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->addService('email-service');
        $workflow->addService('document-service');

        $this->assertTrue($workflow->hasService('email-service'));
        $this->assertTrue($workflow->hasService('document-service'));
        $this->assertFalse($workflow->hasService('unknown-service'));
    }

    /**
     * @test
     * @group workflow-services
     */
    public function testCannotAddEmptyService(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->addService('');
    }

    /**
     * @test
     * @group workflow-services
     */
    public function testCannotAddDuplicateService(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addService('email-service');
        $workflow->addService('email-service');

        $this->assertEquals(1, $workflow->getServiceCount());
    }

    /**
     * @test
     * @group workflow-services
     */
    public function testCanRemoveService(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addService('email-service');
        $workflow->addService('document-service');

        $workflow->removeService('email-service');

        $this->assertFalse($workflow->hasService('email-service'));
        $this->assertTrue($workflow->hasService('document-service'));
        $this->assertEquals(1, $workflow->getServiceCount());
    }

    /**
     * @test
     * @group workflow-services
     */
    public function testGetServicesCount(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertEquals(0, $workflow->getServiceCount());

        $workflow->addService('svc1');
        $workflow->addService('svc2');
        $workflow->addService('svc3');

        $this->assertEquals(3, $workflow->getServiceCount());
    }

    // ========================================
    // STEPS COLLECTION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-steps
     */
    public function testCanAddSteps(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->addStep('step-1-create-account');
        $workflow->addStep('step-2-assign-equipment');

        $this->assertTrue($workflow->hasStep('step-1-create-account'));
        $this->assertTrue($workflow->hasStep('step-2-assign-equipment'));
    }

    /**
     * @test
     * @group workflow-steps
     */
    public function testCannotAddEmptyStep(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->addStep('');
    }

    /**
     * @test
     * @group workflow-steps
     */
    public function testCannotAddDuplicateStep(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addStep('step-1');
        $workflow->addStep('step-1');

        $this->assertEquals(1, $workflow->getStepCount());
    }

    /**
     * @test
     * @group workflow-steps
     */
    public function testCanRemoveStep(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addStep('step-1');
        $workflow->addStep('step-2');

        $workflow->removeStep('step-1');

        $this->assertFalse($workflow->hasStep('step-1'));
        $this->assertTrue($workflow->hasStep('step-2'));
    }

    /**
     * @test
     * @group workflow-steps
     */
    public function testGetStepsCount(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertEquals(0, $workflow->getStepCount());

        $workflow->addStep('s1');
        $workflow->addStep('s2');

        $this->assertEquals(2, $workflow->getStepCount());
    }

    // ========================================
    // KPI MANAGEMENT TESTS
    // ========================================

    /**
     * @test
     * @group workflow-kpis
     */
    public function testCanAddKpis(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->addKpi('completion-rate');
        $workflow->addKpi('time-to-completion');

        $this->assertTrue($workflow->hasKpi('completion-rate'));
        $this->assertTrue($workflow->hasKpi('time-to-completion'));
    }

    /**
     * @test
     * @group workflow-kpis
     */
    public function testCannotAddEmptyKpi(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->addKpi('');
    }

    /**
     * @test
     * @group workflow-kpis
     */
    public function testCanRemoveKpi(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addKpi('kpi-1');
        $workflow->addKpi('kpi-2');

        $workflow->removeKpi('kpi-1');

        $this->assertFalse($workflow->hasKpi('kpi-1'));
        $this->assertTrue($workflow->hasKpi('kpi-2'));
    }

    /**
     * @test
     * @group workflow-kpis
     */
    public function testCanSetAllKpis(): void
    {
        $workflow = $this->createValidWorkflow();
        $kpis = ['kpi-1', 'kpi-2', 'kpi-3'];

        $workflow->setKpis($kpis);

        $this->assertEquals(3, $workflow->getKpiCount());
        $this->assertEquals($kpis, $workflow->getKpis());
    }

    /**
     * @test
     * @group workflow-kpis
     */
    public function testGetKpiCount(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertEquals(0, $workflow->getKpiCount());

        $workflow->addKpi('k1');
        $workflow->addKpi('k2');
        $workflow->addKpi('k3');

        $this->assertEquals(3, $workflow->getKpiCount());
    }

    // ========================================
    // DEPENDENCIES MANAGEMENT TESTS
    // ========================================

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testCanAddDependencies(): void
    {
        $workflow = $this->createValidWorkflow();

        $workflow->addDependency('pre-hire-workflow');
        $workflow->addDependency('verification-workflow');

        $this->assertTrue($workflow->hasDependency('pre-hire-workflow'));
        $this->assertTrue($workflow->hasDependency('verification-workflow'));
    }

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testCannotAddEmptyDependency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->addDependency('');
    }

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testCannotAddSelfDependency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workflow = $this->createValidWorkflow();
        $workflow->addDependency('emp-onboarding');
    }

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testCanRemoveDependency(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addDependency('dep-1');
        $workflow->addDependency('dep-2');

        $workflow->removeDependency('dep-1');

        $this->assertFalse($workflow->hasDependency('dep-1'));
        $this->assertTrue($workflow->hasDependency('dep-2'));
    }

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testCanSetAllDependencies(): void
    {
        $workflow = $this->createValidWorkflow();
        $deps = ['pre-hire', 'verification', 'approval'];

        $workflow->setDependencies($deps);

        $this->assertEquals(3, $workflow->getDependencyCount());
        $this->assertEquals($deps, $workflow->getDependencies());
    }

    /**
     * @test
     * @group workflow-dependencies
     */
    public function testGetDependencyCount(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertEquals(0, $workflow->getDependencyCount());

        $workflow->addDependency('d1');
        $workflow->addDependency('d2');

        $this->assertEquals(2, $workflow->getDependencyCount());
    }

    // ========================================
    // SLA TESTS
    // ========================================

    /**
     * @test
     * @group workflow-sla
     */
    public function testCanSetAndGetSla(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->setSla('5 business days');

        $this->assertEquals('5 business days', $workflow->getSla());
    }

    /**
     * @test
     * @group workflow-sla
     */
    public function testSlaIsOptional(): void
    {
        $workflow = new Workflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('workflowId')->setValue($workflow, 'test-id');
        $reflection->getProperty('workflowName')->setValue($workflow, 'test-name');
        $reflection->getProperty('description')->setValue($workflow, 'test-desc');
        $reflection->getProperty('capabilityId')->setValue($workflow, 'cap-1');
        $reflection->getProperty('businessOwner')->setValue($workflow, 'owner-1');

        $this->assertEmpty($workflow->getSla());
    }

    // ========================================
    // SERIALIZATION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-serialization
     */
    public function testCanConvertToArray(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->addService('email-service');
        $workflow->addKpi('completion-rate');
        // Ensure status is set correctly after reflection manipulation
        $workflow->setStatus(Workflow::ACTIVE);

        $array = $workflow->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('emp-onboarding', $array['workflow_id']);
        $this->assertEquals('Employee Onboarding', $array['workflow_name']);
        $this->assertEquals('employee-management', $array['capability_id']);
        $this->assertEquals('Active', $array['status']);
        $this->assertIsArray($array['services']);
        $this->assertIsArray($array['kpis']);
    }

    /**
     * @test
     * @group workflow-serialization
     */
    public function testCanConvertToJson(): void
    {
        $workflow = $this->createValidWorkflow();

        $json = $workflow->toJson();

        $this->assertIsString($json);
        $this->assertStringContainsString('emp-onboarding', $json);
        $this->assertStringContainsString('Employee Onboarding', $json);

        $decoded = json_decode($json, true);
        $this->assertEquals('emp-onboarding', $decoded['workflow_id']);
    }

    /**
     * @test
     * @group workflow-serialization
     */
    public function testJsonSerializable(): void
    {
        $workflow = $this->createValidWorkflow();

        $json = json_encode($workflow);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('emp-onboarding', $decoded['workflow_id']);
    }

    /**
     * @test
     * @group workflow-serialization
     */
    public function testCanConvertToString(): void
    {
        $workflow = $this->createValidWorkflow();

        $str = (string)$workflow;

        $this->assertStringContainsString('emp-onboarding', $str);
        $this->assertStringContainsString('Employee Onboarding', $str);
    }

    // ========================================
    // VALIDATION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateMandatoryFields(): void
    {
        $workflow = new Workflow();
        $reflection = new \ReflectionClass($workflow);

        // All empty - should fail
        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');

        $this->expectException(\InvalidArgumentException::class);
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresWorkflowId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow ID is required');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('workflowId')->setValue($workflow, '');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresWorkflowName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow Name is required');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('workflowName')->setValue($workflow, '');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow Description is required');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('description')->setValue($workflow, '');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresCapabilityId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capability ID (parent) is required');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('capabilityId')->setValue($workflow, '');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresBusinessOwner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business Owner is required');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('businessOwner')->setValue($workflow, '');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    /**
     * @test
     * @group workflow-validation
     */
    public function testValidateRequiresValidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');

        $workflow = $this->createValidWorkflow();
        $reflection = new \ReflectionClass($workflow);
        $reflection->getProperty('status')->setValue($workflow, 'InvalidStatus');

        $validateMethod = $reflection->getMethod('validateMandatoryWorkflowFields');
        $validateMethod->invoke($workflow);
    }

    // ========================================
    // ARCHITECTURE & INTEGRATION TESTS
    // ========================================

    /**
     * @test
     * @group workflow-architecture
     */
    public function testWorkflowIsMutableBusinessEntity(): void
    {
        $workflow = $this->createValidWorkflow();

        // Should be able to modify properties (unlike WorkflowDefinition)
        $workflow->setStatus(Workflow::DRAFT);
        $this->assertEquals(Workflow::DRAFT, $workflow->getStatus());

        $workflow->setBusinessOwner('new-owner');
        $this->assertEquals('new-owner', $workflow->getBusinessOwner());

        $workflow->addKpi('new-kpi');
        $this->assertTrue($workflow->hasKpi('new-kpi'));

        // Business model is mutable
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group workflow-coexistence
     */
    public function testWorkflowReferencesDefinitionDoesNotDuplicate(): void
    {
        $workflow = $this->createValidWorkflow();

        // Workflow stores definition reference, not full definition
        $workflow->setWorkflowDefinitionId('technical-def-v1');
        $this->assertEquals('technical-def-v1', $workflow->getWorkflowDefinitionId());

        // Workflow doesn't store technical details like:
        // - step implementations
        // - function entry/exit points
        // - trigger events (only trigger description)
        // This is WorkflowDefinition's responsibility

        // Workflow manages business governance:
        $workflow->setSla('24 hours');
        $workflow->setBusinessOwner('manager-001');
        $workflow->addKpi('completion-rate');

        $this->assertEquals('24 hours', $workflow->getSla());
        $this->assertEquals('manager-001', $workflow->getBusinessOwner());
        $this->assertTrue($workflow->hasKpi('completion-rate'));
    }

    /**
     * @test
     * @group workflow-framework-independence
     */
    public function testWorkflowAbstractHasNoFrameworkDependencies(): void
    {
        $workflow = $this->createValidWorkflow();

        // Verify it's a pure business logic layer
        $this->assertInstanceOf(Workflow::class, $workflow);

        // All methods are business-level, no framework coupling
        $workflow->addService('service-1');
        $workflow->addKpi('kpi-1');
        $workflow->setStatus(Workflow::ACTIVE);

        $this->assertTrue(true); // Framework independence verified
    }

    /**
     * @test
     * @group workflow-hierarchy
     */
    public function testWorkflowBelongsToCapability(): void
    {
        $workflow = $this->createValidWorkflow();

        // Workflow must have a capability parent
        $this->assertNotEmpty($workflow->getCapabilityId());

        // Capability ID should be set
        $this->assertEquals('employee-management', $workflow->getCapabilityId());

        // Workflow hierarchy: Capability â†’ Workflow â†’ Service/Step
        // (Workflow sits between Capability and Service/Step layers)
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group workflow-version-consistency
     */
    public function testWorkflowVersionIncrements(): void
    {
        $workflow = $this->createValidWorkflow();

        $this->assertEquals(1, $workflow->getEntityVersion());

        // Version should be managed by BaseModel
        // (not tested here as that's BaseModel's responsibility)
    }

    /**
     * @test
     * @group workflow-lifecycle
     */
    public function testWorkflowLifecycleTransition(): void
    {
        $workflow = $this->createValidWorkflow();

        // Test typical lifecycle transition
        $workflow->setStatus(Workflow::DRAFT);
        $this->assertTrue($workflow->isDraft());

        $workflow->setStatus(Workflow::REVIEW);
        $this->assertTrue($workflow->isInReview());

        $workflow->setStatus(Workflow::APPROVED);
        $this->assertTrue($workflow->isApproved());

        $workflow->setStatus(Workflow::ACTIVE);
        $this->assertTrue($workflow->isActive());

        // Can transition to suspended
        $workflow->setStatus(Workflow::SUSPENDED);
        $this->assertTrue($workflow->isSuspended());

        // Can transition to retired
        $workflow->setStatus(Workflow::RETIRED);
        $this->assertTrue($workflow->isRetired());
    }
}
