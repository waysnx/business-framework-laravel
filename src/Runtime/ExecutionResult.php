<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Runtime;

/**
 * ExecutionResult
 *
 * Immutable, structured outcome of one Business Function execution through
 * BusinessFunctionRuntime::run() (WBF-SPEC-0006, decision D1).
 *
 * - status         one of ExecutionStatus::ALL
 * - errorCategory  WBF error category (ValidationError, AuthorizationError,
 *                  ResourceNotFound, BusinessRuleViolation, or the
 *                  exception's own category / InternalSystemError)
 * - errorCode      machine-readable reason where defined (authorization:
 *                  no_authorizer | invalid_decision | denied)
 * - errorMessage   PUBLIC message. For execution_failure it is always the
 *                  fixed SAFE_MESSAGE plus the reference, never the raw
 *                  exception message (BF-ERR-03, ADR-0009 amendment
 *                  2026-11-01). For the other failure statuses it is the
 *                  message the application or framework wrote for callers.
 * - errorReference reference for support and log correlation (the
 *                  executionId); set on every failed result
 * - failedPhase    pipeline phase that failed (BusinessFunctionAbstract::PHASE_*)
 *
 * The original exception is available via getException() for server-side
 * diagnostics. It is deliberately NOT included in toArray()/jsonSerialize().
 *
 * @package WaysNX\BusinessFramework\Runtime
 */
final class ExecutionResult implements \JsonSerializable
{
    /** Stable public message for unexpected failures (identical in the Node runtime). */
    public const SAFE_MESSAGE = 'An internal error occurred while executing the business function.';

    public readonly ?string $errorMessage;
    public readonly ?string $errorReference;

    /**
     * @param array<int, array{type: string, functionId: string}> $events
     */
    public function __construct(
        public readonly string $status,
        public readonly string $functionId,
        public readonly string $executionId,
        public readonly ?array $response = null,
        public readonly ?string $errorCategory = null,
        public readonly ?string $errorCode = null,
        ?string $errorMessage = null,
        public readonly ?string $failedPhase = null,
        public readonly array $events = [],
        public readonly float $durationMs = 0.0,
        private readonly ?\Throwable $exception = null
    ) {
        if (!in_array($status, ExecutionStatus::ALL, true)) {
            throw new \InvalidArgumentException("ExecutionResult status '{$status}' is not a WBF result status");
        }

        $failed = $status !== ExecutionStatus::SUCCESS;
        $this->errorReference = $failed ? $executionId : null;
        // Invariant: an execution_failure never carries a caller-supplied or
        // exception-derived message, whoever constructs the result.
        $this->errorMessage = $status === ExecutionStatus::EXECUTION_FAILURE
            ? self::safeMessage($executionId)
            : ($failed ? $errorMessage : null);
    }

    public static function safeMessage(string $reference): string
    {
        return self::SAFE_MESSAGE . ' Reference: ' . $reference;
    }

    public function succeeded(): bool
    {
        return $this->status === ExecutionStatus::SUCCESS;
    }

    public function failed(): bool
    {
        return $this->status !== ExecutionStatus::SUCCESS;
    }

    /**
     * Original exception (server-side diagnostics only; never serialized).
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'functionId' => $this->functionId,
            'executionId' => $this->executionId,
            'response' => $this->response,
            'error' => $this->failed()
                ? [
                    'category' => $this->errorCategory,
                    'code' => $this->errorCode,
                    'message' => $this->errorMessage,
                    'reference' => $this->errorReference,
                ]
                : null,
            'failedPhase' => $this->failedPhase,
            'events' => $this->events,
            'durationMs' => $this->durationMs,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
