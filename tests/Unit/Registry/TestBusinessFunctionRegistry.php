<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Registry;

use WaysNX\BusinessFramework\Registry\BusinessFunctionDefinition;
use WaysNX\BusinessFramework\Registry\BusinessFunctionRegistry;

/**
 * TestBusinessFunctionRegistry
 *
 * Test implementation of BusinessFunctionRegistry that tracks hook calls.
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Registry
 */
class TestBusinessFunctionRegistry extends BusinessFunctionRegistry
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
     * @param BusinessFunctionDefinition $definition The function definition
     *
     * @return void
     */
    protected function beforeRegister(BusinessFunctionDefinition $definition): void
    {
        $this->beforeRegisterCalled = true;
    }

    /**
     * Override afterRegister to track calls
     *
     * @param BusinessFunctionDefinition $definition The function definition
     *
     * @return void
     */
    protected function afterRegister(BusinessFunctionDefinition $definition): void
    {
        $this->afterRegisterCalled = true;
    }

    /**
     * Override beforeUnregister to track calls
     *
     * @param BusinessFunctionDefinition $definition The function definition
     *
     * @return void
     */
    protected function beforeUnregister(BusinessFunctionDefinition $definition): void
    {
        $this->beforeUnregisterCalled = true;
    }

    /**
     * Override afterUnregister to track calls
     *
     * @param BusinessFunctionDefinition $definition The function definition
     *
     * @return void
     */
    protected function afterUnregister(BusinessFunctionDefinition $definition): void
    {
        $this->afterUnregisterCalled = true;
    }
}
