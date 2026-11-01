<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Runtime;

use Ramsey\Uuid\Uuid;
use WaysNX\BusinessFramework\Core\BusinessFunctionAbstract;
use WaysNX\BusinessFramework\Exceptions\AuthorizationException;
use WaysNX\BusinessFramework\Exceptions\CategorizedExceptionInterface;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;

/**
 * BusinessFunctionRuntime
 *
 * Minimal observability wrapper around the business function execution.
 *
 * Key Finding: BusinessFunctionAbstract now provides the public execute() method
 * that orchestrates the canonical pipeline. The Runtime is framework-specific code
 * that wraps execution for observability, metrics, and event tracking.
 *
 * This demonstrates that a Runtime doesn't need to be a complex Kernel.
 * It's simply observability and metrics collection around the existing
 * BusinessFunction::execute() method.
 *
 * Pipeline is implemented in BusinessFunctionAbstract::execute():
 * 1. validateRequest() - BF-VAL-01
 * 2. checkAuthorization() - BF-SEC-01
 * 3. evaluateBusinessRules() - BF-BR-01
 * 4. executeBusiness() - BF-EXE-01
 * 5. publishEvents() - BF-EVT-01
 * 6. transformToResponse()
 *
 * @package WaysNX\BusinessFramework\Runtime
 */
class BusinessFunctionRuntime
{
    private array $context = [];
    private array $events = [];
    private ?\Throwable $executionError = null;

    /** @var callable(string, array<string, mixed>): void */
    private $errorReporter;

    /**
     * @param (callable(string $message, array<string, mixed> $context): void)|null $errorReporter
     *        Server-side sink for unexpected failures reported by run(). The
     *        signature matches PSR-3 LoggerInterface::error(), so a Laravel
     *        or other PSR-3 logger can be passed directly:
     *        new BusinessFunctionRuntime([Log::channel('stack'), 'error']).
     *        $context contains: reference, executionId, functionId,
     *        failedPhase, errorCategory, exceptionClass and exception (the
     *        Throwable, PSR-3 convention). Default: PHP error_log() with the
     *        full exception string including the stack trace.
     */
    public function __construct(?callable $errorReporter = null)
    {
        $this->errorReporter = $errorReporter ?? [self::class, 'defaultErrorReporter'];
    }

    /**
     * Replace the server-side error reporter (see __construct()).
     *
     * @param callable(string $message, array<string, mixed> $context): void $errorReporter
     */
    public function setErrorReporter(callable $errorReporter): static
    {
        $this->errorReporter = $errorReporter;

        return $this;
    }

    /**
     * Default reporter: never loses the diagnostic detail that the public
     * result no longer carries.
     *
     * @param array<string, mixed> $context
     */
    public static function defaultErrorReporter(string $message, array $context): void
    {
        $exception = $context['exception'] ?? null;
        error_log($message . ($exception instanceof \Throwable ? "\n" . (string) $exception : ''));
    }

    /**
     * Execute a BusinessFunction with observability
     *
     * @param BusinessFunctionInterface $businessFunction The BF to execute
     * @param array $request The incoming request
     * @param mixed $caller Optional caller context
     * @return array The response
     *
     * @throws \InvalidArgumentException Validation failure
     * @throws \RuntimeException Authorization or business rule failure
     */
    public function execute(
        BusinessFunctionInterface $businessFunction,
        array $request,
        mixed $caller = null
    ): array {
        $this->context = [];
        $this->events = [];
        $this->executionError = null;

        $this->context['functionId'] = $businessFunction->getFunctionId();
        $this->context['startTime'] = microtime(true);

        try {
            // BusinessFunctionAbstract::execute() orchestrates the pipeline
            $response = $businessFunction->execute($request, $caller);

            $this->context['endTime'] = microtime(true);
            $this->context['executionTime'] = $this->context['endTime'] - $this->context['startTime'];
            $this->context['status'] = 'success';

            // Collect published events
            foreach ($businessFunction->getEventsPublished() as $eventType) {
                $this->events[] = ['type' => $eventType, 'functionId' => $businessFunction->getFunctionId()];
            }

            return $response;
        } catch (\Throwable $e) {
            $this->executionError = $e;
            $this->context['endTime'] = microtime(true);
            $this->context['executionTime'] = $this->context['endTime'] - $this->context['startTime'];
            $this->context['status'] = 'failed';
            $this->context['error'] = $e->getMessage();
            $this->context['errorClass'] = $e::class;
            throw $e;
        }
    }

    /**
     * Execute a Business Function and return a structured ExecutionResult
     * instead of throwing (WBF-SPEC-0006, decision D1 approved 2026-11-01).
     *
     * execute() is unchanged and still throws; run() wraps it. Expected
     * business outcomes AND unexpected internal errors are both returned as a
     * result: an unexpected error becomes execution_failure, never success.
     *
     * Classification order (WBF-SPEC-0006 §3):
     * 1. Request is not an array → validation_failure (the pipeline is not entered).
     * 2. CategorizedExceptionInterface → by category (ValidationError,
     *    AuthorizationError, ResourceNotFound, BusinessRuleViolation; any other
     *    category → execution_failure).
     * 3. Plain exception, controlled fallback by the pipeline phase that failed
     *    (BusinessFunctionAbstract::getFailedPhase()):
     *    - businessRules phase                         → business_rule_failure
     *    - validation phase + \InvalidArgumentException → validation_failure
     *      (the documented validation exception of execute())
     *    There is no authorization-phase fallback: a plain exception from
     *    checkAuthorization() or an authorizer is unexpected (rule 4). Deny
     *    explicitly with AuthorizationException.
     * 4. Anything else → execution_failure (category InternalSystemError).
     *
     * Messages (ADR-0009 amendment, BF-ERR-03): an execution_failure result
     * carries only ExecutionResult::SAFE_MESSAGE and a reference (the
     * executionId); the exception is sent to the error reporter with the same
     * reference and stays available via getException(). Other failure
     * statuses keep the message written for callers.
     *
     * @param BusinessFunctionInterface $businessFunction The BF to execute
     * @param mixed $request The incoming request (must be an array)
     * @param mixed $caller Optional caller context (identity input, not proof of authorization)
     */
    public function run(
        BusinessFunctionInterface $businessFunction,
        mixed $request,
        mixed $caller = null
    ): ExecutionResult {
        $executionId = (string) Uuid::uuid4();
        $start = hrtime(true);
        $functionId = $businessFunction->getFunctionId();

        if (!is_array($request)) {
            $this->context = [
                'functionId' => $functionId,
                'status' => 'failed',
                'error' => 'Request must be an array',
            ];
            $this->events = [];
            $this->executionError = null;

            return new ExecutionResult(
                status: ExecutionStatus::VALIDATION_FAILURE,
                functionId: $functionId,
                executionId: $executionId,
                errorCategory: CategorizedExceptionInterface::CATEGORY_VALIDATION,
                errorMessage: 'Request must be an array, got ' . get_debug_type($request),
                failedPhase: BusinessFunctionAbstract::PHASE_VALIDATION,
                durationMs: (hrtime(true) - $start) / 1_000_000
            );
        }

        try {
            $response = $this->execute($businessFunction, $request, $caller);

            return new ExecutionResult(
                status: ExecutionStatus::SUCCESS,
                functionId: $functionId,
                executionId: $executionId,
                response: $response,
                events: $this->events,
                durationMs: (hrtime(true) - $start) / 1_000_000
            );
        } catch (\Throwable $e) {
            $phase = $businessFunction instanceof BusinessFunctionAbstract
                ? $businessFunction->getFailedPhase()
                : null;
            [$status, $category] = self::classify($e, $phase);

            if ($status === ExecutionStatus::EXECUTION_FAILURE) {
                // The public result carries only ExecutionResult::SAFE_MESSAGE
                // and the reference (BF-ERR-03). The full detail goes to the
                // server-side reporter, correlated by the same reference.
                $this->reportUnexpected($e, $functionId, $executionId, $phase, $category);
            }

            return new ExecutionResult(
                status: $status,
                functionId: $functionId,
                executionId: $executionId,
                errorCategory: $category,
                errorCode: $e instanceof AuthorizationException ? $e->getReasonCode() : null,
                errorMessage: $e->getMessage(),
                failedPhase: $phase,
                durationMs: (hrtime(true) - $start) / 1_000_000,
                exception: $e
            );
        }
    }

    private function reportUnexpected(
        \Throwable $e,
        string $functionId,
        string $executionId,
        ?string $phase,
        string $category
    ): void {
        $message = "WBF execution_failure in business function '{$functionId}' (reference {$executionId})";
        $context = [
            'reference' => $executionId,
            'executionId' => $executionId,
            'functionId' => $functionId,
            'failedPhase' => $phase,
            'errorCategory' => $category,
            'exceptionClass' => $e::class,
            'exception' => $e,
        ];

        try {
            ($this->errorReporter)($message, $context);
        } catch (\Throwable $reporterError) {
            // A failing reporter must neither hide the original error nor make
            // run() throw. Fall back to the default sink for both.
            self::defaultErrorReporter($message, $context);
            self::defaultErrorReporter(
                "WBF error reporter failed while reporting reference {$executionId}",
                ['exception' => $reporterError]
            );
        }
    }

    /**
     * Classify a failure into [status, errorCategory] (see run()).
     *
     * @return array{0: string, 1: string}
     */
    public static function classify(\Throwable $e, ?string $failedPhase): array
    {
        if ($e instanceof CategorizedExceptionInterface) {
            $category = $e->getCategory();
            $status = match ($category) {
                CategorizedExceptionInterface::CATEGORY_VALIDATION => ExecutionStatus::VALIDATION_FAILURE,
                CategorizedExceptionInterface::CATEGORY_AUTHORIZATION => ExecutionStatus::AUTHORIZATION_FAILURE,
                CategorizedExceptionInterface::CATEGORY_RESOURCE_NOT_FOUND => ExecutionStatus::NOT_FOUND,
                CategorizedExceptionInterface::CATEGORY_BUSINESS_RULE => ExecutionStatus::BUSINESS_RULE_FAILURE,
                default => ExecutionStatus::EXECUTION_FAILURE,
            };

            return [$status, $category];
        }

        // Controlled phase-based fallbacks for plain (uncategorized) exceptions.
        // There is deliberately NO authorization-phase fallback: only an
        // explicit denial (AuthorizationException, i.e. a categorized
        // AuthorizationError) is authorization_failure. Anything else thrown
        // while authorizing is unexpected and therefore execution_failure.
        if ($failedPhase === BusinessFunctionAbstract::PHASE_BUSINESS_RULES) {
            return [ExecutionStatus::BUSINESS_RULE_FAILURE, CategorizedExceptionInterface::CATEGORY_BUSINESS_RULE];
        }
        if ($failedPhase === BusinessFunctionAbstract::PHASE_VALIDATION && $e instanceof \InvalidArgumentException) {
            return [ExecutionStatus::VALIDATION_FAILURE, CategorizedExceptionInterface::CATEGORY_VALIDATION];
        }

        return [ExecutionStatus::EXECUTION_FAILURE, 'InternalSystemError'];
    }

    public function getContext(): array
    {
        return $this->context;
    }
    public function getEvents(): array
    {
        return $this->events;
    }
    public function getExecutionError(): ?\Throwable
    {
        return $this->executionError;
    }
    public function wasSuccessful(): bool
    {
        return $this->executionError === null;
    }
}
