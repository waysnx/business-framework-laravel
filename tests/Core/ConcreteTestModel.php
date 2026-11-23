<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Core\Tests;

use WaysNX\BusinessFramework\Core\BaseModelAbstract;

/**
 * ConcreteTestModel
 *
 * Concrete implementation of BaseModelAbstract for testing purposes.
 * Implements the abstract methods required by BaseModelAbstract.
 */
class ConcreteTestModel extends BaseModelAbstract
{
    /**
     * Generate a new unique identifier
     */
    protected function generateUuid(): string|int
    {
        // Simple UUID-like string for testing
        static $counter = 0;
        return 'test-uuid-' . (++$counter);
    }

    /**
     * Initialize audit timestamps
     */
    protected function initializeAuditTimestamps(): void
    {
        $this->createdAt = new \DateTime('2024-01-01 12:00:00');
    }

    /**
     * Update the last modification timestamp
     */
    protected function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime('2024-01-01 13:00:00');
    }

    /**
     * Update the deletion timestamp
     */
    protected function deleteTimestamp(): void
    {
        $this->deletedAt = new \DateTime('2024-01-01 14:00:00');
    }

    /**
     * Convert the entity to an array representation
     */
    public function toArray(): array
    {
        return [
            'entity_id' => $this->getEntityId(),
            'entity_type' => $this->getEntityType(),
            'entity_version' => $this->getEntityVersion(),
            'created_by' => $this->getCreatedBy(),
            'created_at' => $this->getCreatedAt(),
            'updated_by' => $this->getUpdatedBy(),
            'updated_at' => $this->getUpdatedAt(),
            'deleted_by' => $this->getDeletedBy(),
            'deleted_at' => $this->getDeletedAt(),
            'is_deleted' => $this->isDeleted(),
            'metadata' => $this->getMetadata(),
        ];
    }

    /**
     * Convert the entity to JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
