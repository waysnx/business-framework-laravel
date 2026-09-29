<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Registry;

use WaysNX\BusinessFramework\Registry\ValidationDefinition;
use WaysNX\BusinessFramework\Registry\ValidationRegistry;

/**
 * TestValidationRegistry
 *
 * Test implementation of ValidationRegistry that tracks hook calls.
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Registry
 */
class TestValidationRegistry extends ValidationRegistry
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
     * @param ValidationDefinition $definition The validation definition
     *
     * @return void
     */
    protected function beforeRegister(ValidationDefinition $definition): void
    {
        $this->beforeRegisterCalled = true;
    }

    /**
     * Override afterRegister to track calls
     *
     * @param ValidationDefinition $definition The validation definition
     *
     * @return void
     */
    protected function afterRegister(ValidationDefinition $definition): void
    {
        $this->afterRegisterCalled = true;
    }

    /**
     * Override beforeUnregister to track calls
     *
     * @param ValidationDefinition $definition The validation definition
     *
     * @return void
     */
    protected function beforeUnregister(ValidationDefinition $definition): void
    {
        $this->beforeUnregisterCalled = true;
    }

    /**
     * Override afterUnregister to track calls
     *
     * @param ValidationDefinition $definition The validation definition
     *
     * @return void
     */
    protected function afterUnregister(ValidationDefinition $definition): void
    {
        $this->afterUnregisterCalled = true;
    }
}
