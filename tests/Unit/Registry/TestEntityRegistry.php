<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Registry;

use WaysNX\BusinessFramework\Registry\EntityDefinition;
use WaysNX\BusinessFramework\Registry\EntityRegistry;

/**
 * TestEntityRegistry
 *
 * Test implementation of EntityRegistry that tracks hook calls.
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Registry
 */
class TestEntityRegistry extends EntityRegistry
{
    /**
     * Track if beforeRegister was called
     *
     * @var bool
     */
    public bool $beforeRegisterCalled = false;

    /**
     * Track if afterRegister was called
     *
     * @var bool
     */
    public bool $afterRegisterCalled = false;

    /**
     * Track if beforeUnregister was called
     *
     * @var bool
     */
    public bool $beforeUnregisterCalled = false;

    /**
     * Track if afterUnregister was called
     *
     * @var bool
     */
    public bool $afterUnregisterCalled = false;

    /**
     * Override beforeRegister to track calls
     *
     * @param EntityDefinition $definition The entity definition
     *
     * @return void
     */
    protected function beforeRegister(EntityDefinition $definition): void
    {
        $this->beforeRegisterCalled = true;
    }

    /**
     * Override afterRegister to track calls
     *
     * @param EntityDefinition $definition The entity definition
     *
     * @return void
     */
    protected function afterRegister(EntityDefinition $definition): void
    {
        $this->afterRegisterCalled = true;
    }

    /**
     * Override beforeUnregister to track calls
     *
     * @param EntityDefinition $definition The entity definition
     *
     * @return void
     */
    protected function beforeUnregister(EntityDefinition $definition): void
    {
        $this->beforeUnregisterCalled = true;
    }

    /**
     * Override afterUnregister to track calls
     *
     * @param EntityDefinition $definition The entity definition
     *
     * @return void
     */
    protected function afterUnregister(EntityDefinition $definition): void
    {
        $this->afterUnregisterCalled = true;
    }
}
