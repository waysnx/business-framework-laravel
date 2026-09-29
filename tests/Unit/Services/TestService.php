<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Services;

use WaysNX\BusinessFramework\Services\BaseService;

/**
 * TestService - Test service with hook tracking
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Services
 */
class TestService extends BaseService
{
    /**
     * Track if validateCreate was called
     *
     * @var bool
     */
    public bool $validateCreateCalled = false;

    /**
     * Track if validateUpdate was called
     *
     * @var bool
     */
    public bool $validateUpdateCalled = false;

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
     * Order of hook calls
     *
     * @var int
     */
    public int $validateCreateOrder = 0;

    /**
     * Order of hook calls
     *
     * @var int
     */
    public int $beforeCreateOrder = 0;

    /**
     * Order of hook calls
     *
     * @var int
     */
    public int $afterCreateOrder = 0;

    /**
     * Modify data in before hook
     *
     * @var bool
     */
    public bool $modifyDataInBeforeCreate = false;

    /**
     * Should validation fail
     *
     * @var bool
     */
    public bool $shouldValidateCreateFail = false;

    /**
     * Should validation fail
     *
     * @var bool
     */
    public bool $shouldValidateUpdateFail = false;

    /**
     * Counter for hook order tracking
     *
     * @var int
     */
    private int $hookCounter = 0;

    /**
     * Override validateCreate hook
     *
     * @param array &$data The data
     *
     * @return void
     */
    protected function validateCreate(array &$data): void
    {
        $this->validateCreateCalled = true;
        $this->validateCreateOrder = ++$this->hookCounter;

        if ($this->shouldValidateCreateFail) {
            throw new \Exception('Validation failed');
        }
    }

    /**
     * Override validateUpdate hook
     *
     * @param array &$data The data
     *
     * @return void
     */
    protected function validateUpdate(array &$data): void
    {
        $this->validateUpdateCalled = true;

        if ($this->shouldValidateUpdateFail) {
            throw new \Exception('Validation failed');
        }
    }

    /**
     * Override beforeCreate hook
     *
     * @param array &$data The data
     *
     * @return void
     */
    protected function beforeCreate(array &$data): void
    {
        $this->beforeCreateCalled = true;
        $this->beforeCreateOrder = ++$this->hookCounter;

        if ($this->modifyDataInBeforeCreate) {
            $data['modified'] = true;
        }
    }

    /**
     * Override afterCreate hook
     *
     * @param mixed $entity The entity
     *
     * @return void
     */
    protected function afterCreate(mixed $entity): void
    {
        $this->afterCreateCalled = true;
        $this->afterCreateOrder = ++$this->hookCounter;
    }

    /**
     * Override beforeUpdate hook
     *
     * @param string|int $id The entity ID
     *
     * @param array &$data The data
     *
     * @return void
     */
    protected function beforeUpdate(string|int $id, array &$data): void
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
     * @param string|int $id The entity ID
     *
     * @return void
     */
    protected function beforeDelete(string|int $id): void
    {
        $this->beforeDeleteCalled = true;
    }

    /**
     * Override afterDelete hook
     *
     * @param string|int $id The entity ID
     *
     * @return void
     */
    protected function afterDelete(string|int $id): void
    {
        $this->afterDeleteCalled = true;
    }

    /**
     * Override beforeRestore hook
     *
     * @param string|int $id The entity ID
     *
     * @return void
     */
    protected function beforeRestore(string|int $id): void
    {
        $this->beforeRestoreCalled = true;
    }

    /**
     * Override afterRestore hook
     *
     * @param string|int $id The entity ID
     *
     * @return void
     */
    protected function afterRestore(string|int $id): void
    {
        $this->afterRestoreCalled = true;
    }

    /**
     * Make transaction public for testing
     *
     * @param callable $callback The callback
     *
     * @return mixed
     */
    public function transaction(callable $callback): mixed
    {
        return parent::transaction($callback);
    }
}
