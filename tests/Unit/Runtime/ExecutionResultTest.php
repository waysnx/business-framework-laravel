<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Authorization\AuthorizationDecision;
use WaysNX\BusinessFramework\BusinessFunctions\ApplyLeave;
use WaysNX\BusinessFramework\Contracts\AuthorizerInterface;
use WaysNX\BusinessFramework\Core\BusinessFunctionAbstract;
use WaysNX\BusinessFramework\Exceptions\AuthorizationException;
use WaysNX\BusinessFramework\Exceptions\BusinessRuleViolationException;
use WaysNX\BusinessFramework\Exceptions\CategorizedExceptionInterface;
use WaysNX\BusinessFramework\Exceptions\ModuleNotFoundException;
use WaysNX\BusinessFramework\Exceptions\ResourceNotFoundException;
use WaysNX\BusinessFramework\Exceptions\ValidationFailedException;
use WaysNX\BusinessFramework\Models\BusinessFunction;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;
use WaysNX\BusinessFramework\Runtime\ExecutionResult;
use WaysNX\BusinessFramework\Runtime\ExecutionStatus;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;

/**
 * Regression tests for decision D1 (approved 2026-11-01):
 * BusinessFunctionRuntime::run(): ExecutionResult with the six WBF statuses,
 * categorized exceptions first, pipeline-phase fallback for plain exceptions,
 * and an unchanged throwing execute().
 */
final class ExecutionResultTest extends TestCase
{
    /**
     * Build a BF whose phases throw the given exception (or succeed).
     *
     * @param array<string, \Throwable> $throws phase => exception
     */
    private function fn(array $throws = [], bool $authorize = true): BusinessFunction
    {
        $bf = new class ($throws) extends BusinessFunction {
            public function __construct(private array $throws)
            {
                $this->functionId = 'T.RES.RUN.RUN';
                $this->functionName = 'Run';
                $this->functionVersion = '1.0.0';
                $this->module = 'T';
                $this->domain = 'RES';
                $this->capability = 'RUN';
                $this->lifecycleStatus = 'Draft';
                $this->description = 'Result contract test double';
                $this->businessOwner = 'QA';
                $this->technicalOwner = 'QA';
                $this->tags = [];
                $this->classification = 'Core';
                $this->visibility = 'Internal';
                $this->dependencies = [];
                $this->eventsPublished = ['RunCompleted'];
                $this->eventsConsumed = [];
                $this->keywords = [];
                $this->requestContract = ['fields' => []];
                $this->responseContract = ['fields' => ['ok' => ['type' => 'boolean']]];
                $this->errorCategories = [];
                $this->validationRules = [];
                $this->authorizationRequirements = [];
                $this->businessRules = [];
                $this->observabilityRequirements = [];
                $this->auditRequirements = [];
                $this->initializeBusinessFunction();
            }

            private function maybeThrow(string $phase): void
            {
                if (isset($this->throws[$phase])) {
                    throw $this->throws[$phase];
                }
            }

            protected function checkAuthorization(array $request, mixed $caller = null): void
            {
                $this->maybeThrow('authorization');
                parent::checkAuthorization($request, $caller);
            }

            protected function validateRequest(array $request): void
            {
                $this->maybeThrow('validation');
            }

            protected function evaluateBusinessRules(array $request): void
            {
                $this->maybeThrow('businessRules');
            }

            protected function executeBusiness(array $request): array
            {
                $this->maybeThrow('execution');
                return ['ok' => true];
            }
        };

        if ($authorize) {
            $bf->setAuthorizer(new class implements AuthorizerInterface {
                public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
                {
                    return AuthorizationDecision::allow();
                }
            });
        }

        return $bf;
    }

    /** @var array<int, array{0: string, 1: array<string, mixed>}> */
    private array $reported = [];

    private function runtime(): BusinessFunctionRuntime
    {
        return new BusinessFunctionRuntime(function (string $message, array $context): void {
            $this->reported[] = [$message, $context];
        });
    }

    private function run1(BusinessFunction $bf, mixed $request = []): ExecutionResult
    {
        return $this->runtime()->run($bf, $request, 'user-1');
    }

    public function testSuccess(): void
    {
        $r = $this->run1($this->fn());

        $this->assertSame(ExecutionStatus::SUCCESS, $r->status);
        $this->assertTrue($r->succeeded());
        $this->assertSame(['ok' => true], $r->response);
        $this->assertSame([['type' => 'RunCompleted', 'functionId' => 'T.RES.RUN.RUN']], $r->events);
        $this->assertNull($r->errorCategory);
    }

    public function testNonArrayRequestIsValidationFailureWithoutEnteringPipeline(): void
    {
        foreach ([null, 'text', 42] as $request) {
            $r = $this->run1($this->fn(['execution' => new \RuntimeException('must not run')]), $request);
            $this->assertSame(ExecutionStatus::VALIDATION_FAILURE, $r->status);
            $this->assertSame('ValidationError', $r->errorCategory);
            $this->assertSame(BusinessFunctionAbstract::PHASE_VALIDATION, $r->failedPhase);
        }
    }

    public function testCategorizedValidationFailure(): void
    {
        $r = $this->run1($this->fn(['validation' => new ValidationFailedException('bad field')]));

        $this->assertSame(ExecutionStatus::VALIDATION_FAILURE, $r->status);
        $this->assertSame('ValidationError', $r->errorCategory);
    }

    public function testPlainInvalidArgumentInValidationPhaseIsValidationFailure(): void
    {
        $r = $this->run1($this->fn(['validation' => new \InvalidArgumentException('bad')]));

        $this->assertSame(ExecutionStatus::VALIDATION_FAILURE, $r->status);
    }

    public function testOtherPlainExceptionInValidationPhaseIsExecutionFailure(): void
    {
        $r = $this->run1($this->fn(['validation' => new \LogicException('bug in validator')]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
    }

    public function testAuthorizationFailuresCarryReasonCode(): void
    {
        $r = $this->run1($this->fn([], authorize: false));

        $this->assertSame(ExecutionStatus::AUTHORIZATION_FAILURE, $r->status);
        $this->assertSame('AuthorizationError', $r->errorCategory);
        $this->assertSame(AuthorizationException::REASON_NO_AUTHORIZER, $r->errorCode);
        $this->assertSame(BusinessFunctionAbstract::PHASE_AUTHORIZATION, $r->failedPhase);
    }

    public function testExplicitDenialFromCheckAuthorizationOverrideIsAuthorizationFailure(): void
    {
        $r = $this->runtime()->run(new ApplyLeave(), [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => (new \DateTimeImmutable('today'))->modify('+10 days')->format('Y-m-d'),
            'endDate' => (new \DateTimeImmutable('today'))->modify('+12 days')->format('Y-m-d'),
        ], ['id' => 'EMP-2026-002', 'role' => 'Employee']);

        $this->assertSame(ExecutionStatus::AUTHORIZATION_FAILURE, $r->status);
        $this->assertSame(AuthorizationException::REASON_DENIED, $r->errorCode);
        $this->assertStringContainsString('Employees can only apply leave for themselves', (string) $r->errorMessage);
    }

    public function testNotFoundOnlyForMissingBusinessResource(): void
    {
        $r = $this->run1($this->fn(['execution' => new ResourceNotFoundException('Order', 'O-1')]));

        $this->assertSame(ExecutionStatus::NOT_FOUND, $r->status);
        $this->assertSame('ResourceNotFound', $r->errorCategory);
        $this->assertSame("Order 'O-1' not found", $r->errorMessage);
    }

    public function testRegistryNotFoundIsExecutionFailureNotNotFound(): void
    {
        $r = $this->run1($this->fn(['execution' => new ModuleNotFoundException("Module 'X' not found")]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
    }

    public function testPlainExceptionFromBusinessRulesPhaseIsBusinessRuleFailure(): void
    {
        $r = $this->run1($this->fn(['businessRules' => new \RuntimeException('balance insufficient')]));

        $this->assertSame(ExecutionStatus::BUSINESS_RULE_FAILURE, $r->status);
        $this->assertSame('BusinessRuleViolation', $r->errorCategory);
        $this->assertSame(BusinessFunctionAbstract::PHASE_BUSINESS_RULES, $r->failedPhase);
    }

    public function testCategorizedBusinessRuleViolationFromExecutionPhaseIsBusinessRuleFailure(): void
    {
        $r = $this->run1($this->fn(['execution' => new BusinessRuleViolationException('limit exceeded')]));

        $this->assertSame(ExecutionStatus::BUSINESS_RULE_FAILURE, $r->status);
        $this->assertSame(BusinessFunctionAbstract::PHASE_EXECUTION, $r->failedPhase);
    }

    public function testCategoryTakesPrecedenceOverPhase(): void
    {
        $r = $this->run1($this->fn(['businessRules' => new ResourceNotFoundException('Employee', 'E-9')]));

        $this->assertSame(ExecutionStatus::NOT_FOUND, $r->status);
    }

    public function testUnexpectedExceptionIsExecutionFailureDistinctFromBusinessRuleFailure(): void
    {
        $rule = $this->run1($this->fn(['businessRules' => new \RuntimeException('rule')]));
        $boom = $this->run1($this->fn(['execution' => new \RuntimeException('boom')]));

        $this->assertSame(ExecutionStatus::BUSINESS_RULE_FAILURE, $rule->status);
        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $boom->status);
        $this->assertSame('InternalSystemError', $boom->errorCategory);
        $this->assertNotSame($rule->status, $boom->status);
    }

    public function testErrorIsNotSerializedButIsAvailableForDiagnostics(): void
    {
        $e = new \RuntimeException('boom');
        $r = $this->run1($this->fn(['execution' => $e]));

        $this->assertSame($e, $r->getException());
        $this->assertArrayNotHasKey('exception', $r->toArray());
        $this->assertSame(
            [
                'category' => 'InternalSystemError',
                'code' => null,
                'message' => ExecutionResult::safeMessage($r->executionId),
                'reference' => $r->executionId,
            ],
            $r->toArray()['error']
        );
    }

    public function testExecuteStillThrowsTheOriginalException(): void
    {
        $e = new \RuntimeException('rule');
        $bf = $this->fn(['businessRules' => $e]);

        try {
            (new BusinessFunctionRuntime())->execute($bf, []);
            $this->fail('execute() must keep throwing');
        } catch (\RuntimeException $caught) {
            $this->assertSame($e, $caught);
        }
        $this->assertSame(BusinessFunctionAbstract::PHASE_BUSINESS_RULES, $bf->getFailedPhase());
    }

    public function testCategorizedExceptionsExtendExistingBaseExceptions(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new ValidationFailedException('x'));
        $this->assertInstanceOf(\RuntimeException::class, new BusinessRuleViolationException('x'));
        $this->assertInstanceOf(\RuntimeException::class, new ResourceNotFoundException('T', '1'));
        $this->assertInstanceOf(\RuntimeException::class, new AuthorizationException('x'));
        foreach ([new ValidationFailedException('x'), new BusinessRuleViolationException('x'), new ResourceNotFoundException('T', '1'), new AuthorizationException('x')] as $e) {
            $this->assertInstanceOf(CategorizedExceptionInterface::class, $e);
        }
    }

    public function testFailedPhaseIsResetOnSuccess(): void
    {
        $bf = $this->fn();
        $this->run1($bf);

        $this->assertNull($bf->getFailedPhase());
    }

    public function testResultRejectsUnknownStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ExecutionResult(status: 'failure', functionId: 'F', executionId: 'E');
    }

    public function testStatusesMatchTheWbfContract(): void
    {
        $this->assertSame(
            ['success', 'validation_failure', 'authorization_failure', 'not_found', 'business_rule_failure', 'execution_failure'],
            ExecutionStatus::ALL
        );
    }

    public function testApplyLeaveStatusesThroughRun(): void
    {
        $runtime = $this->runtime();
        $day = fn(int $d) => (new \DateTimeImmutable('today'))->modify("+{$d} days")->format('Y-m-d');
        $employee = ['id' => 'EMP-2026-001', 'role' => 'Employee'];

        $ok = $runtime->run(new ApplyLeave(), ['employeeId' => 'EMP-2026-001', 'leaveType' => 'Annual', 'startDate' => $day(10), 'endDate' => $day(11)], $employee);
        $invalid = $runtime->run(new ApplyLeave(), ['employeeId' => 'EMP-2026-001', 'leaveType' => 'Annual', 'startDate' => $day(12), 'endDate' => $day(11)], $employee);
        $inactive = $runtime->run(new ApplyLeave(), ['employeeId' => 'EMP-2026-003', 'leaveType' => 'Annual', 'startDate' => $day(10), 'endDate' => $day(11)], ['id' => 'EMP-2026-003', 'role' => 'Employee']);

        $this->assertSame(ExecutionStatus::SUCCESS, $ok->status);
        $this->assertSame(ExecutionStatus::VALIDATION_FAILURE, $invalid->status);
        $this->assertSame(ExecutionStatus::BUSINESS_RULE_FAILURE, $inactive->status);
    }

    // ------------------------------------------------------------------
    // Phase 0.1 review (2026-11-01): authorization has NO phase fallback
    // ------------------------------------------------------------------

    public function testPlainExceptionFromCheckAuthorizationOverrideIsExecutionFailure(): void
    {
        $r = $this->run1($this->fn(['authorization' => new \RuntimeException('LDAP timeout at 10.0.0.5')]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
        $this->assertSame('InternalSystemError', $r->errorCategory);
        $this->assertSame(BusinessFunctionAbstract::PHASE_AUTHORIZATION, $r->failedPhase);
        $this->assertNull($r->errorCode);
        $this->assertCount(1, $this->reported);
    }

    public function testPlainExceptionFromAuthorizerIsExecutionFailure(): void
    {
        $bf = $this->fn([], authorize: false);
        $bf->setAuthorizer(new class implements AuthorizerInterface {
            public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
            {
                throw new \RuntimeException('policy store unavailable');
            }
        });

        $r = $this->run1($bf);

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
        $this->assertSame(BusinessFunctionAbstract::PHASE_AUTHORIZATION, $r->failedPhase);
    }

    public function testExplicitDenialsAreAuthorizationFailure(): void
    {
        $denyingAuthorizer = $this->fn([], authorize: false);
        $denyingAuthorizer->setAuthorizer(new class implements AuthorizerInterface {
            public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
            {
                return AuthorizationDecision::deny('not a manager');
            }
        });
        $denyingOverride = $this->fn(['authorization' => new AuthorizationException('not your request')]);

        foreach ([$this->run1($denyingAuthorizer), $this->run1($denyingOverride)] as $r) {
            $this->assertSame(ExecutionStatus::AUTHORIZATION_FAILURE, $r->status);
            $this->assertSame(AuthorizationException::REASON_DENIED, $r->errorCode);
        }
        $this->assertStringContainsString('not a manager', (string) $this->run1($denyingAuthorizer)->errorMessage);
        $this->assertSame([], $this->reported, 'explicit denials are expected outcomes, not unexpected errors');
    }

    // ------------------------------------------------------------------
    // Phase 0.1 review: controlled validation-phase fallback
    // ------------------------------------------------------------------

    public function testValidationFallbackKeepsTheUserFacingMessage(): void
    {
        $r = $this->run1($this->fn(['validation' => new \InvalidArgumentException('startDate cannot be after endDate')]));

        $this->assertSame(ExecutionStatus::VALIDATION_FAILURE, $r->status);
        $this->assertSame('ValidationError', $r->errorCategory);
        $this->assertSame('startDate cannot be after endDate', $r->errorMessage);
        $this->assertSame([], $this->reported);
    }

    public function testCategorizedExceptionsTakePrecedenceOverTheValidationFallback(): void
    {
        $cases = [
            ExecutionStatus::BUSINESS_RULE_FAILURE => new BusinessRuleViolationException('limit'),
            ExecutionStatus::NOT_FOUND => new ResourceNotFoundException('Employee', 'E-9'),
            ExecutionStatus::AUTHORIZATION_FAILURE => new AuthorizationException('denied'),
            ExecutionStatus::VALIDATION_FAILURE => new ValidationFailedException('bad'),
        ];

        foreach ($cases as $expected => $exception) {
            $r = $this->run1($this->fn(['validation' => $exception]));
            $this->assertSame($expected, $r->status, $exception::class);
            $this->assertSame(BusinessFunctionAbstract::PHASE_VALIDATION, $r->failedPhase);
        }
    }

    public function testValidationFallbackDoesNotApplyOutsideTheValidationPhase(): void
    {
        $r = $this->run1($this->fn(['execution' => new \InvalidArgumentException('bad argument deep inside')]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
    }

    // ------------------------------------------------------------------
    // ADR-0009 amendment (BF-ERR-03): no internal details in public results
    // ------------------------------------------------------------------

    public function testUnexpectedExceptionDetailsAreNotExposed(): void
    {
        $secret = 'SQLSTATE[HY000] [1045] Access denied for user app@10.0.0.7 (password=hunter2) in /var/www/app/Db.php';
        $e = new \RuntimeException($secret, 0, new \LogicException('inner detail'));
        $r = $this->run1($this->fn(['execution' => $e]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
        $this->assertSame(ExecutionResult::safeMessage($r->executionId), $r->errorMessage);
        $this->assertSame($r->executionId, $r->errorReference);

        $public = json_encode($r, JSON_THROW_ON_ERROR) . var_export($r->toArray(), true);
        foreach (['SQLSTATE', 'hunter2', '10.0.0.7', '/var/www', 'inner detail', 'RuntimeException', 'LogicException', __FILE__, '#0'] as $needle) {
            $this->assertStringNotContainsString($needle, $public, "public result leaks '{$needle}'");
        }
        $this->assertSame($e, $r->getException(), 'diagnostics remain available server-side');
    }

    public function testUnexpectedExceptionIsReportedServerSideWithTheSameReference(): void
    {
        $e = new \RuntimeException('disk full on /srv/data');
        $r = $this->run1($this->fn(['execution' => $e]));

        $this->assertCount(1, $this->reported);
        [$message, $context] = $this->reported[0];
        $this->assertStringContainsString($r->executionId, $message);
        $this->assertSame($r->errorReference, $context['reference']);
        $this->assertSame($e, $context['exception']);
        $this->assertSame(\RuntimeException::class, $context['exceptionClass']);
        $this->assertSame('T.RES.RUN.RUN', $context['functionId']);
        $this->assertSame(BusinessFunctionAbstract::PHASE_EXECUTION, $context['failedPhase']);
    }

    public function testExpectedFailuresKeepTheirMessagesAndAreNotReported(): void
    {
        $rule = $this->run1($this->fn(['businessRules' => new \RuntimeException('Insufficient leave balance')]));
        $missing = $this->run1($this->fn(['execution' => new ResourceNotFoundException('Order', 'O-1')]));
        $categorizedRule = $this->run1($this->fn(['execution' => new BusinessRuleViolationException('Limit exceeded')]));

        $this->assertSame('Insufficient leave balance', $rule->errorMessage);
        $this->assertSame("Order 'O-1' not found", $missing->errorMessage);
        $this->assertSame('Limit exceeded', $categorizedRule->errorMessage);
        foreach ([$rule, $missing, $categorizedRule] as $r) {
            $this->assertSame($r->executionId, $r->errorReference);
        }
        $this->assertSame([], $this->reported);
    }

    public function testCategorizedExceptionWithANonBusinessCategoryIsAlsoSafe(): void
    {
        $e = new class ('registry file /etc/wbf/registry.php is corrupt') extends \RuntimeException implements CategorizedExceptionInterface {
            public function getCategory(): string
            {
                return 'RegistryError';
            }
        };

        $r = $this->run1($this->fn(['execution' => $e]));

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
        $this->assertSame('RegistryError', $r->errorCategory);
        $this->assertStringNotContainsString('/etc/wbf', json_encode($r, JSON_THROW_ON_ERROR));
        $this->assertCount(1, $this->reported);
    }

    public function testExecutionFailureResultNeverCarriesASuppliedMessage(): void
    {
        $r = new ExecutionResult(status: ExecutionStatus::EXECUTION_FAILURE, functionId: 'F', executionId: 'E-1', errorMessage: 'raw internal');

        $this->assertSame(ExecutionResult::safeMessage('E-1'), $r->errorMessage);
    }

    public function testSuccessCarriesNoErrorMessageOrReference(): void
    {
        $r = $this->run1($this->fn());

        $this->assertNull($r->errorMessage);
        $this->assertNull($r->errorReference);
        $this->assertNull($r->toArray()['error']);
    }

    public function testDefaultReporterLogsTheFullExceptionServerSide(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'wbf-log-');
        $previous = ini_set('error_log', $log);

        try {
            $r = (new BusinessFunctionRuntime())->run($this->fn(['execution' => new \RuntimeException('raw detail 42')]), []);
            $logged = (string) file_get_contents($log);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            @unlink($log);
        }

        $this->assertStringNotContainsString('raw detail 42', (string) $r->errorMessage);
        $this->assertStringContainsString('raw detail 42', $logged);
        $this->assertStringContainsString(\RuntimeException::class, $logged);
        $this->assertStringContainsString($r->executionId, $logged);
        $this->assertStringContainsString('#0 ', $logged, 'stack trace is preserved in the log');
    }

    public function testFailingReporterDoesNotBreakRunOrLoseTheDetail(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'wbf-log-');
        $previous = ini_set('error_log', $log);

        try {
            $runtime = new BusinessFunctionRuntime(static function (): void {
                throw new \RuntimeException('logger down');
            });
            $r = $runtime->run($this->fn(['execution' => new \RuntimeException('original detail')]), []);
            $logged = (string) file_get_contents($log);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            @unlink($log);
        }

        $this->assertSame(ExecutionStatus::EXECUTION_FAILURE, $r->status);
        $this->assertStringContainsString('original detail', $logged);
        $this->assertStringContainsString('logger down', $logged);
    }
}
