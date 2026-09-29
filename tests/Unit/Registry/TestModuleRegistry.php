<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Registry;

use WaysNX\BusinessFramework\Registry\ModuleDefinition;
use WaysNX\BusinessFramework\Registry\ModuleRegistry;

/**
 * TestModuleRegistry
 *
 * Test implementation of ModuleRegistry that tracks hook calls.
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Registry
 */
class TestModuleRegistry extends ModuleRegistry
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
     * @param ModuleDefinition $definition The module definition
     *
     * @return void
     */
    protected function beforeRegister(ModuleDefinition $definition): void
    {
        $this->beforeRegisterCalled = true;
    }

    /**
     * Override afterRegister to track calls
     *
     * @param ModuleDefinition $definition The module definition
     *
     * @return void
     */
    protected function afterRegister(ModuleDefinition $definition): void
    {
        $this->afterRegisterCalled = true;
    }

    /**
     * Override beforeUnregister to track calls
     *
     * @param ModuleDefinition $definition The module definition
     *
     * @return void
     */
    protected function beforeUnregister(ModuleDefinition $definition): void
    {
        $this->beforeUnregisterCalled = true;
    }

    /**
     * Override afterUnregister to track calls
     *
     * @param ModuleDefinition $definition The module definition
     *
     * @return void
     */
    protected function afterUnregister(ModuleDefinition $definition): void
    {
        $this->afterUnregisterCalled = true;
    }
}
