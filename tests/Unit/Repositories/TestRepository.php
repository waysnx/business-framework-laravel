<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Repositories;

use WaysNX\BusinessFramework\Repositories\BaseRepository;

/**
 * TestRepository - Test repository with hook tracking
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Repositories
 */
class TestRepository extends BaseRepository
{
    /**
     * Test entity for find operations
     *
     * @var TestEntity|null
     */
    private ?TestEntity $testEntity = null;

    /**
     * Track if beforeCreate was called
     *
     * @var bool
     */
    public bool $beforeCreateCalled = false;

    /**
     * Track if afterCreate was called
     *
     * @var bool
     */
    public bool $afterCreateCalled = false;

    /**
     * Track if beforeUpdate was called
     *
     * @var bool
     */
    public bool $beforeUpdateCalled = false;

    /**
     * Track if afterUpdate was called
     *
     * @var bool
     */
    public bool $afterUpdateCalled = false;

    /**
     * Track if beforeDelete was called
     *
     * @var bool
     */
    public bool $beforeDeleteCalled = false;

    /**
     * Track if afterDelete was called
     *
     * @var bool
     */
    public bool $afterDeleteCalled = false;

    /**
     * Track if beforeRestore was called
     *
     * @var bool
     */
    public bool $beforeRestoreCalled = false;

    /**
     * Track if afterRestore was called
     *
     * @var bool
     */
    public bool $afterRestoreCalled = false;

    /**
     * Track if applyFilters was called
     *
     * @var bool
     */
    public bool $applyFiltersCalled = false;

    /**
     * Track if applySorting was called
     *
     * @var bool
     */
    public bool $applySortingCalled = false;

    /**
     * Set a test entity for find operations
     *
     * @param TestEntity $entity The test entity
     *
     * @return void
     */
    public function setTestEntity(TestEntity $entity): void
    {
        $this->testEntity = $entity;
    }

    /**
     * Override find to use test entity
     *
     * @param string|int $id The entity ID
     *
     * @return mixed|null
     */
    public function find(string|int $id): mixed
    {
        if ($id === 'test-id' && $this->testEntity !== null) {
            return $this->testEntity;
        }
        return null;
    }

    /**
     * Override beforeCreate hook
     *
     * @param array &$data The data array
     *
     * @return void
     */
    protected function beforeCreate(array &$data): void
    {
        $this->beforeCreateCalled = true;
    }

    /**
     * Override afterCreate hook
     *
     * @param mixed $entity The created entity
     *
     * @return void
     */
    protected function afterCreate(mixed $entity): void
    {
        $this->afterCreateCalled = true;
    }

    /**
     * Override beforeUpdate hook
     *
     * @param mixed $entity The entity
     * @param array &$data The data
     *
     * @return void
     */
    protected function beforeUpdate(mixed $entity, array &$data): void
    {
        $this->beforeUpdateCalled = true;
    }

    /**
     * Override afterUpdate hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function afterUpdate(mixed $entity): void
    {
        $this->afterUpdateCalled = true;
    }

    /**
     * Override beforeDelete hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function beforeDelete(mixed $entity): void
    {
        $this->beforeDeleteCalled = true;
    }

    /**
     * Override afterDelete hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function afterDelete(mixed $entity): void
    {
        $this->afterDeleteCalled = true;
    }

    /**
     * Override beforeRestore hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function beforeRestore(mixed $entity): void
    {
        $this->beforeRestoreCalled = true;
    }

    /**
     * Override afterRestore hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function afterRestore(mixed $entity): void
    {
        $this->afterRestoreCalled = true;
    }

    /**
     * Override applyFilters hook
     *
     * @param array $filters The filters
     *
     * @return void
     */
    protected function applyFilters(array $filters): void
    {
        $this->applyFiltersCalled = true;
    }

    /**
     * Override applySorting hook
     *
     * @param array $sortParams The sort parameters
     *
     * @return void
     */
    protected function applySorting(array $sortParams): void
    {
        $this->applySortingCalled = true;
    }
}
