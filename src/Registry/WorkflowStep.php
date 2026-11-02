<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Registry;

use WaysNX\BusinessFramework\Exceptions\WorkflowDefinitionException;

/**
 * WorkflowStep
 *
 * Immutable value object representing a workflow step definition.
 *
 * Encapsulates a single step within a workflow process.
 * Each step references a business function and defines its position in the workflow.
 *
 * Purpose:
 * Represent a single step in a workflow with its business function reference,
 * sequencing, and transition metadata.
 *
 * Responsibilities:
 * - Store step identification (id, sequence)
 * - Store function reference (businessFunctionId)
 * - Store transition definitions
 * - Store metadata (conditions, retry policy, timeout)
 * - Provide type-safe access to step properties
 *
 * Usage:
 * ```php
 * $step = new WorkflowStep(
 *     id: 'step-1',
 *     businessFunctionId: 'create-customer',
 *     sequence: 1,
 *     description: 'Create the customer record',
 *     transitions: [
 *         ['target' => 'step-2', 'condition' => 'success']
 *     ],
 *     metadata: ['retry' => 3, 'timeout' => 300]
 * );
 * ```
 *
 * Immutability:
 * Once created, WorkflowStep cannot be modified.
 *
 * @package WaysNX\BusinessFramework\Registry
 */
class WorkflowStep
{
    /**
     * The step identifier
     *
     * @var string
     */
    public readonly string $id;

    /**
     * The referenced business function ID
     *
     * @var string
     */
    public readonly string $businessFunctionId;

    /**
     * The sequence in workflow (1-based)
     *
     * @var int
     */
    public readonly int $sequence;

    /**
     * The step description
     *
     * @var string
     */
    public readonly string $description;

    /**
     * Transition definitions to other steps
     *
     * @var array<array>
     */
    public readonly array $transitions;

    /**
     * Step metadata (conditions, retry, timeout, etc.)
     *
     * @var array
     */
    public readonly array $metadata;

    /**
     * Initialize a new WorkflowStep
     *

     * Sequence contract (WBF-SPEC-0007, decision D8, approved 2026-11-01):
     * `sequence` is REQUIRED. There is no implicit default of 0. A missing,
     * non-integer or negative value is rejected here with a
     * WorkflowDefinitionException. 0 is a valid sequence. The parameter is
     * declared nullable/untyped only so that an omitted or invalid value
     * produces a clear WBF error instead of a PHP TypeError or silent coercion.
     *
     * Migration: `new WorkflowStep($id, $fn)` (previously sequence 0) must now
     * pass an explicit sequence, e.g. `new WorkflowStep($id, $fn, 0)`.
     *
     * @param string $id The step identifier
     * @param string $businessFunctionId The referenced business function ID
     * @param int|null $sequence The sequence in workflow (required, integer >= 0)
     * @param string $description The step description
     * @param array<array> $transitions Transition definitions
     * @param array $metadata Step metadata
     *
     * @throws WorkflowDefinitionException If the sequence is missing, not an integer, or negative
     */
    public function __construct(
        string $id,
        string $businessFunctionId,
        mixed $sequence = null,
        string $description = '',
        array $transitions = [],
        array $metadata = []
    ) {
        if ($sequence === null) {
            throw new WorkflowDefinitionException(
                "Workflow step '{$id}': sequence is required",
                WorkflowDefinitionException::REASON_MISSING_SEQUENCE
            );
        }
        if (!is_int($sequence)) {
            throw new WorkflowDefinitionException(
                "Workflow step '{$id}': sequence must be an integer, got " . get_debug_type($sequence),
                WorkflowDefinitionException::REASON_INVALID_SEQUENCE
            );
        }
        if ($sequence < 0) {
            throw new WorkflowDefinitionException(
                "Workflow step '{$id}': sequence must be >= 0, got {$sequence}",
                WorkflowDefinitionException::REASON_NEGATIVE_SEQUENCE
            );
        }

        $this->id = $id;
        $this->businessFunctionId = $businessFunctionId;
        $this->sequence = $sequence;
        $this->description = $description;
        $this->transitions = $transitions;
        $this->metadata = $metadata;
    }

    /**
     * Convert step to array
     *

     * @return array The step as array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessFunctionId' => $this->businessFunctionId,
            'sequence' => $this->sequence,
            'description' => $this->description,
            'transitions' => $this->transitions,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get metadata value
     *

     * @param string $key The metadata key
     * @param mixed $default Default value
     *

     * @return mixed The metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata key exists
     *

     * @param string $key The metadata key
     *

     * @return bool True if key exists
     */
    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    /**
     * Get retry policy from metadata
     *

     * @return int|null The retry count
     */
    public function getRetryPolicy(): ?int
    {
        return $this->getMetadataValue('retry');
    }

    /**
     * Get timeout from metadata
     *

     * @return int|null The timeout in seconds
     */
    public function getTimeout(): ?int
    {
        return $this->getMetadataValue('timeout');
    }

    /**
     * Get conditions from metadata
     *

     * @return array The conditions
     */
    public function getConditions(): array
    {
        return (array) $this->getMetadataValue('conditions', []);
    }
}
