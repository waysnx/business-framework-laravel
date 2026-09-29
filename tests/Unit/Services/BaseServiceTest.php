<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Contracts\RepositoryInterface;
use WaysNX\BusinessFramework\Contracts\ServiceInterface;
use WaysNX\BusinessFramework\Exceptions\EntityNotFoundException;

/**
 * BaseServiceTest
 *
 * PHPUnit test suite for BaseService functionality.
 *
 * Tests cover:
 * - Service initialization
 * - CRUD delegation to repository
 * - Lifecycle hook execution
 * - Validation hook execution
 * - Repository interaction
 * - Exception propagation
 * - Repository swapping
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Services
 */
class BaseServiceTest extends TestCase
{
    /**
     * Test service instance
     *
     * @var TestService
     */
    private TestService $service;

    /**
     * Mock repository
     *
     * @var MockRepository
     */
    private MockRepository $repository;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = new MockRepository(new TestEntity());
        $this->service = new TestService($this->repository);
    }

    /**
     * Test service instantiation with repository
     *
     * @return void
     */
    public function testServiceInstantiationWithRepository(): void
    {
        $this->assertInstanceOf(ServiceInterface::class, $this->service);
        $this->assertSame($this->repository, $this->service->getRepository());
    }

    /**
     * Test service implements ServiceInterface
     *
     * @return void
     */
    public function testServiceImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(ServiceInterface::class, $this->service);
    }

    /**
     * Test repository can be set after instantiation
     *
     * @return void
     */
    public function testRepositoryCanBeSetAfterInstantiation(): void
    {
        $newRepository = new MockRepository(new TestEntity());
        $this->service->setRepository($newRepository);

        $this->assertSame($newRepository, $this->service->getRepository());
    }

    /**
     * Test setRepository returns self for method chaining
     *
     * @return void
     */
    public function testSetRepositoryReturnsSelfForChaining(): void
    {
        $newRepository = new MockRepository(new TestEntity());
        $result = $this->service->setRepository($newRepository);

        $this->assertSame($this->service, $result);
    }

    /**
     * Test create() delegates to repository
     *
     * @return void
     */
    public function testCreateDelegatesToRepository(): void
    {
        $data = ['name' => 'Test Entity'];
        $entity = $this->service->create($data);

        $this->assertNotNull($entity);
        $this->assertTrue($this->repository->createCalled);
    }

    /**
     * Test create() calls validateCreate hook
     *
     * @return void
     */
    public function testCreateCallsValidateCreateHook(): void
    {
        $this->service->create(['name' => 'Test']);

        $this->assertTrue($this->service->validateCreateCalled);
    }

    /**
     * Test create() calls beforeCreate hook
     *

     * @return void
     */
    public function testCreateCallsBeforeCreateHook(): void
    {
        $this->service->create(['name' => 'Test']);

        $this->assertTrue($this->service->beforeCreateCalled);
    }

    /**
     * Test create() calls afterCreate hook
     *

     * @return void
     */
    public function testCreateCallsAfterCreateHook(): void
    {
        $this->service->create(['name' => 'Test']);

        $this->assertTrue($this->service->afterCreateCalled);
    }

    /**
     * Test create() hook execution order
     *

     * @return void
     */
    public function testCreateHookExecutionOrder(): void
    {
        $this->service->create(['name' => 'Test']);

        // Validate should be called first
        $this->assertLessThan(
            $this->service->beforeCreateOrder,
            $this->service->validateCreateOrder
        );

        // Before should be called before after
        $this->assertLessThan(
            $this->service->afterCreateOrder,
            $this->service->beforeCreateOrder
        );
    }

    /**
     * Test update() delegates to repository
     *

     * @return void
     */
    public function testUpdateDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $entity = $this->service->update('test-id', ['name' => 'Updated']);

        $this->assertNotNull($entity);
        $this->assertTrue($this->repository->updateCalled);
    }

    /**
     * Test update() throws exception if entity not found
     *

     * @return void
     */
    public function testUpdateThrowsExceptionIfNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->service->update('non-existent', ['name' => 'Updated']);
    }

    /**
     * Test update() calls validateUpdate hook
     *

     * @return void
     */
    public function testUpdateCallsValidateUpdateHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->update('test-id', ['name' => 'Updated']);

        $this->assertTrue($this->service->validateUpdateCalled);
    }

    /**
     * Test update() calls beforeUpdate hook
     *

     * @return void
     */
    public function testUpdateCallsBeforeUpdateHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->update('test-id', ['name' => 'Updated']);

        $this->assertTrue($this->service->beforeUpdateCalled);
    }

    /**
     * Test update() calls afterUpdate hook
     *

     * @return void
     */
    public function testUpdateCallsAfterUpdateHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->update('test-id', ['name' => 'Updated']);

        $this->assertTrue($this->service->afterUpdateCalled);
    }

    /**
     * Test delete() delegates to repository
     *

     * @return void
     */
    public function testDeleteDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $result = $this->service->delete('test-id');

        $this->assertTrue($result);
        $this->assertTrue($this->repository->deleteCalled);
    }

    /**
     * Test delete() throws exception if entity not found
     *

     * @return void
     */
    public function testDeleteThrowsExceptionIfNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->service->delete('non-existent');
    }

    /**
     * Test delete() calls beforeDelete hook
     *

     * @return void
     */
    public function testDeleteCallsBeforeDeleteHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->delete('test-id');

        $this->assertTrue($this->service->beforeDeleteCalled);
    }

    /**
     * Test delete() calls afterDelete hook
     *

     * @return void
     */
    public function testDeleteCallsAfterDeleteHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->delete('test-id');

        $this->assertTrue($this->service->afterDeleteCalled);
    }

    /**
     * Test restore() delegates to repository
     *

     * @return void
     */
    public function testRestoreDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $result = $this->service->restore('test-id');

        $this->assertTrue($result);
        $this->assertTrue($this->repository->restoreCalled);
    }

    /**
     * Test restore() throws exception if entity not found
     *

     * @return void
     */
    public function testRestoreThrowsExceptionIfNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->service->restore('non-existent');
    }

    /**
     * Test restore() calls beforeRestore hook
     *

     * @return void
     */
    public function testRestoreCallsBeforeRestoreHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->restore('test-id');

        $this->assertTrue($this->service->beforeRestoreCalled);
    }

    /**
     * Test restore() calls afterRestore hook
     *

     * @return void
     */
    public function testRestoreCallsAfterRestoreHook(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->restore('test-id');

        $this->assertTrue($this->service->afterRestoreCalled);
    }

    /**
     * Test find() delegates to repository
     *

     * @return void
     */
    public function testFindDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $entity = $this->service->find('test-id');

        $this->assertNotNull($entity);
    }

    /**
     * Test findOrFail() delegates to repository
     *

     * @return void
     */
    public function testFindOrFailDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $entity = $this->service->findOrFail('test-id');

        $this->assertNotNull($entity);
    }

    /**
     * Test findOrFail() throws exception if not found
     *

     * @return void
     */
    public function testFindOrFailThrowsExceptionIfNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->service->findOrFail('non-existent');
    }

    /**
     * Test all() delegates to repository
     *

     * @return void
     */
    public function testAllDelegatesToRepository(): void
    {
        $entities = $this->service->all();

        $this->assertIsArray($entities);
    }

    /**
     * Test paginate() delegates to repository
     *

     * @return void
     */
    public function testPaginateDelegatesToRepository(): void
    {
        $result = $this->service->paginate(15, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    /**
     * Test exists() delegates to repository
     *

     * @return void
     */
    public function testExistsDelegatesToRepository(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $exists = $this->service->exists('test-id');

        $this->assertTrue($exists);
    }

    /**
     * Test count() delegates to repository
     *

     * @return void
     */
    public function testCountDelegatesToRepository(): void
    {
        $count = $this->service->count();

        $this->assertIsInt($count);
    }

    /**
     * Test beforeCreate data modification
     *

     * @return void
     */
    public function testBeforeCreateCanModifyData(): void
    {
        $this->service->modifyDataInBeforeCreate = true;
        $this->service->create(['name' => 'Test']);

        // Repository should receive modified data
        $this->assertTrue($this->repository->lastCreateDataWasModified);
    }

    /**
     * Test validateCreate throws exception
     *

     * @return void
     */
    public function testValidateCreateThrowsException(): void
    {
        $this->service->shouldValidateCreateFail = true;

        $this->expectException(\Exception::class);
        $this->service->create(['name' => 'Test']);
    }

    /**
     * Test validateUpdate throws exception
     *

     * @return void
     */
    public function testValidateUpdateThrowsException(): void
    {
        $this->repository->setTestEntity(new TestEntity());
        $this->service->shouldValidateUpdateFail = true;

        $this->expectException(\Exception::class);
        $this->service->update('test-id', ['name' => 'Updated']);
    }

    /**
     * Test transaction support
     *

     * @return void
     */
    public function testTransactionSupport(): void
    {
        // The transaction method is protected on BaseService
        // We test it through MockRepository which has a public wrapper
        $result = $this->repository->publicTransaction(function () {
            return 'result';
        });

        $this->assertEquals('result', $result);
    }
}
