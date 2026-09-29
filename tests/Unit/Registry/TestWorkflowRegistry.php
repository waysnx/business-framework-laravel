<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Registry;

use WaysNX\BusinessFramework\Registry\WorkflowDefinition;
use WaysNX\BusinessFramework\Registry\WorkflowRegistry;

/**
 * TestWorkflowRegistry
 *
 * Test implementation of WorkflowRegistry that tracks hook calls.
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Registry
 */
class TestWorkflowRegistry extends WorkflowRegistry
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
     * @param WorkflowDefinition $definition The workflow definition
     *
     * @return void
     */
    protected function beforeRegister(WorkflowDefinition $definition): void
    {
        $this->beforeRegisterCalled = true;
    }

    /**
     * Override afterRegister to track calls
     *
     * @param WorkflowDefinition $definition The workflow definition
     *
     * @return void
     */
    protected function afterRegister(WorkflowDefinition $definition): void
    {
        $this->afterRegisterCalled = true;
    }

    /**
     * Override beforeUnregister to track calls
     *
     * @param WorkflowDefinition $definition The workflow definition
     *
     * @return void
     */
    protected function beforeUnregister(WorkflowDefinition $definition): void
    {
        $this->beforeUnregisterCalled = true;
    }

    /**
     * Override afterUnregister to track calls
     *
     * @param WorkflowDefinition $definition The workflow definition
     *
     * @return void
     */
    protected function afterUnregister(WorkflowDefinition $definition): void
    {
        $this->afterUnregisterCalled = true;
    }
}
