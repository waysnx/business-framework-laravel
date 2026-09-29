<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Services;

use WaysNX\BusinessFramework\Repositories\BaseRepository;

/**
 * MockRepository - Mock repository for testing services
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Services
 */
class MockRepository extends BaseRepository
{
    /**
     * Test entity for find operations
     *
     * @var TestEntity|null
     */
    private ?TestEntity $testEntity = null;

    /**
     * Track if create was called
     *
     * @var bool
     */
    public bool $createCalled = false;

    /**
     * Track if update was called
     *
     * @var bool
     */
    public bool $updateCalled = false;

    /**
     * Track if delete was called
     *
     * @var bool
     */
    public bool $deleteCalled = false;

    /**
     * Track if restore was called
     *
     * @var bool
     */
    public bool $restoreCalled = false;

    /**
     * Track if create data was modified
     *
     * @var bool
     */
    public bool $lastCreateDataWasModified = false;

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
     * Override create to track calls
     *
     * @param array $data The data
     *
     * @return mixed
     */
    public function create(array $data): mixed
    {
        $this->createCalled = true;
        $this->lastCreateDataWasModified = isset($data['modified']);
        return parent::create($data);
    }

    /**
     * Override update to track calls
     *
     * @param string|int $id The entity ID
     *
     * @param array $data The data
     *
     * @return bool
     */
    public function update(string|int $id, array $data): bool
    {
        $this->updateCalled = true;
        return parent::update($id, $data);
    }

    /**
     * Override delete to track calls
     *
     * @param string|int $id The entity ID
     *
     * @return bool
     */
    public function delete(string|int $id): bool
    {
        $this->deleteCalled = true;
        return parent::delete($id);
    }

    /**
     * Override restore to track calls
     *
     * @param string|int $id The entity ID
     *
     * @return bool
     */
    public function restore(string|int $id): bool
    {
        $this->restoreCalled = true;
        return parent::restore($id);
    }

    /**
     * Public transaction method for testing
     *
     * @param callable $callback
     * @return mixed
     */
    public function publicTransaction(callable $callback): mixed
    {
        return $this->transaction($callback);
    }
}
