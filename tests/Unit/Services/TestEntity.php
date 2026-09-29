<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Services;

use WaysNX\BusinessFramework\Models\BaseModel;

/**
 * TestEntity - Concrete entity for testing
 *
 * Implements minimal query builder interface to support service testing
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\Services
 */
class TestEntity extends BaseModel
{
    /**
     * Query result for get()
     *
     * @var array
     */
    private array $getResult = [];

    /**
     * Query result for count()
     *
     * @var int
     */
    private int $countResult = 0;

    /**
     * Set data for query operations
     *
     * @param array $data
     * @return self
     */
    public function setQueryData(array $data = []): self
    {
        $this->getResult = $data;
        $this->countResult = count($data);
        return $this;
    }

    /**
     * Mock get() - returns query results
     *
     * @return array Query results
     */
    public function get(): array
    {
        return $this->getResult;
    }

    /**
     * Mock count() - returns result count
     *
     * @return int Count of results
     */
    public function count(): int
    {
        return $this->countResult;
    }

    /**
     * Mock offset() - chainable for pagination
     *
     * @param int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        return $this;
    }

    /**
     * Mock limit() - chainable for pagination
     *
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        return $this;
    }
}
