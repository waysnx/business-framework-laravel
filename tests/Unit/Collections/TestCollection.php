<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Collections;

use WaysNX\BusinessFramework\Collections\BaseCollection;

/**
 * TestCollection - Test collection with hook tracking
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Collections
 */
class TestCollection extends BaseCollection
{
    /**
     * Track if beforeTransform was called
     *
     * @var bool
     */
    public bool $beforeTransformCalled = false;

    /**
     * Track if afterTransform was called
     *
     * @var bool
     */
    public bool $afterTransformCalled = false;

    /**
     * Track if beforeSerialize was called
     *
     * @var bool
     */
    public bool $beforeSerializeCalled = false;

    /**
     * Track if afterSerialize was called
     *
     * @var bool
     */
    public bool $afterSerializeCalled = false;

    /**
     * Override beforeTransform
     *
     * @return void
     */
    protected function beforeTransform(): void
    {
        $this->beforeTransformCalled = true;
    }

    /**
     * Override afterTransform
     *
     * @return void
     */
    protected function afterTransform(): void
    {
        $this->afterTransformCalled = true;
    }

    /**
     * Override beforeSerialize
     *
     * @return void
     */
    protected function beforeSerialize(): void
    {
        $this->beforeSerializeCalled = true;
    }

    /**
     * Override afterSerialize
     *
     * @return void
     */
    protected function afterSerialize(): void
    {
        $this->afterSerializeCalled = true;
    }
}
