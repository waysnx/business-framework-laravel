<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * WorkflowDefinitionException
 *
 * Thrown when a workflow step or workflow definition violates the workflow
 * sequencing contract (WBF-SPEC-0007, decision D8):
 * - a step's sequence is missing (null / omitted),
 * - a step's sequence is not an integer,
 * - a step's sequence is negative,
 * - two steps of one workflow definition share a sequence.
 *
 * Extends \InvalidArgumentException because the failure is an invalid
 * constructor argument detected before any execution.
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
class WorkflowDefinitionException extends \InvalidArgumentException
{
    public const REASON_MISSING_SEQUENCE = 'missing_sequence';
    public const REASON_INVALID_SEQUENCE = 'invalid_sequence';
    public const REASON_NEGATIVE_SEQUENCE = 'negative_sequence';
    public const REASON_DUPLICATE_SEQUENCE = 'duplicate_sequence';

    public function __construct(string $message, private string $reasonCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Machine-readable reason: missing_sequence | invalid_sequence |
     * negative_sequence | duplicate_sequence
     */
    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}
