<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Core\Tests;

use WaysNX\BusinessFramework\Core\BusinessFunctionAbstract;

/**
 * ConcreteBusinessFunction
 *
 * Minimal concrete BusinessFunctionAbstract for definition-validation tests.
 * The constructor applies a complete, valid definition, then the given
 * overrides (field => value, or field => null to leave it unset), then calls
 * initializeBusinessFunction() exactly as a real Business Function does.
 */
class ConcreteBusinessFunction extends BusinessFunctionAbstract
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $definition = array_merge(self::validDefinition(), $overrides);

        foreach ($definition as $field => $value) {
            if ($value !== null) {
                $this->{$field} = $value;
            }
        }

        $this->initializeBusinessFunction();
    }

    /**
     * @return array<string, mixed>
     */
    public static function validDefinition(): array
    {
        return [
            'functionId' => 'HR.LEAVE.APPLY.APPLY_LEAVE',
            'functionName' => 'Apply Leave',
            'functionVersion' => '1.0.0',
            'module' => 'HR',
            'domain' => 'LEAVE',
            'capability' => 'APPLY',
            'lifecycleStatus' => 'Released',
            'description' => 'Submit an employee leave request',
            'businessOwner' => 'HR Manager',
            'technicalOwner' => 'HR Team',
            'tags' => ['leave'],
            'classification' => 'Core',
            'visibility' => 'Internal',
            'dependencies' => [],
            'eventsPublished' => ['LeaveRequested'],
            'eventsConsumed' => [],
            'keywords' => ['leave'],
            'requestContract' => ['required' => ['employeeId'], 'fields' => ['employeeId' => ['type' => 'string']]],
            'responseContract' => ['fields' => ['leaveRequestId' => ['type' => 'string']]],
            'errorCategories' => ['ValidationError'],
            'validationRules' => [],
            'authorizationRequirements' => [],
            'businessRules' => [],
            'observabilityRequirements' => [],
            'auditRequirements' => [],
        ];
    }

    protected function generateUuid(): string|int
    {
        return 'test-uuid-' . bin2hex(random_bytes(4));
    }

    protected function initializeAuditTimestamps(): void
    {
        $this->createdAt = new \DateTime('2026-01-01 12:00:00');
    }

    protected function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime('2026-01-01 13:00:00');
    }

    protected function deleteTimestamp(): void
    {
        $this->deletedAt = new \DateTime('2026-01-01 14:00:00');
    }

    public function toArray(): array
    {
        return $this->getCompleteContractDefinition();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    protected function executeBusiness(array $request): array
    {
        return ['ok' => true];
    }
}
