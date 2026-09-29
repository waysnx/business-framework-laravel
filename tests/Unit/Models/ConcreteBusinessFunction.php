<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Models;

use WaysNX\BusinessFramework\Models\BusinessFunction;

/**
 * Concrete test implementation of BusinessFunction
 *
 * Used to test the BusinessFunction class.
 */
class ConcreteBusinessFunction extends BusinessFunction
{
    protected function generateUuid(): string
    {
        // Use Ramsey\Uuid in real implementation
        return 'laravel-uuid-' . uniqid();
    }

    protected function initializeAuditTimestamps(): void
    {
        $this->createdAt = new \DateTimeImmutable('2026-08-15 12:00:00');
        $this->updatedAt = null;
        $this->deletedAt = null;
    }

    protected function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    protected function deleteTimestamp(): void
    {
        $this->deletedAt = new \DateTimeImmutable('now');
    }

    protected function executeBusiness(array $request): array
    {
        return ['status' => 'success', 'result' => $request];
    }
}
