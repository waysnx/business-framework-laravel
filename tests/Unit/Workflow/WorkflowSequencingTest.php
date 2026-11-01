<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Workflow;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Authorization\AuthorizationDecision;
use WaysNX\BusinessFramework\Contracts\AuthorizerInterface;
use WaysNX\BusinessFramework\Exceptions\WorkflowDefinitionException;
use WaysNX\BusinessFramework\Models\BusinessFunction;
use WaysNX\BusinessFramework\Registry\WorkflowDefinition;
use WaysNX\BusinessFramework\Registry\WorkflowRegistry;
use WaysNX\BusinessFramework\Registry\WorkflowStep;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;
use WaysNX\BusinessFramework\Runtime\ExecutionResult;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;
use WaysNX\BusinessFramework\Workflow\WorkflowContext;
use WaysNX\BusinessFramework\Workflow\WorkflowEngine;
use WaysNX\BusinessFramework\Workflow\WorkflowResult;

/**
 * Regression tests for:
 * - LAR-02: determineNextStep() used array order, skipping steps declared out
 *   of sequence while still reporting success.
 * - LAR-03: the default executeBusinessFunction() was empty, so workflows
 *   "completed" without executing any Business Function.
 */
final class WorkflowSequencingTest extends TestCase
{
    /** @var array<string> */
    private array $calls = [];

    private string $errorLog = '';
    private string|false $previousErrorLog = false;

    /**
     * Failing steps run through the engine's default BusinessFunctionRuntime,
     * whose default reporter writes unexpected failures to error_log()
     * (ADR-0009 A1). Redirect it to a temporary file so test runs do not
     * write into the machine's PHP error log.
     */
    protected function setUp(): void
    {
        $this->errorLog = (string) tempnam(sys_get_temp_dir(), 'wbf-wf-log-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        @unlink($this->errorLog);
    }

    private function fn(string $id, bool $fail = false, string $caller = 'wf-user'): BusinessFunction
    {
        $calls = &$this->calls;
        $bf = new class ($id, $fail, $calls) extends BusinessFunction {
            private array $calls;

            public function __construct(string $id, private bool $failExecution, array &$calls)
            {
                $this->calls = &$calls;
                $this->functionId = $id;
                $this->functionName = $id;
                $this->functionVersion = '1.0.0';
                $this->module = 'T';
                $this->domain = 'WF';
                $this->capability = 'STEP';
                $this->lifecycleStatus = 'Draft';
                $this->description = 'Workflow step test double';
                $this->businessOwner = 'QA';
                $this->technicalOwner = 'QA';
                $this->tags = [];
                $this->classification = 'Core';
                $this->visibility = 'Internal';
                $this->dependencies = [];
                $this->eventsPublished = [];
                $this->eventsConsumed = [];
                $this->keywords = [];
                $this->requestContract = ['fields' => []];
                $this->responseContract = ['fields' => []];
                $this->errorCategories = [];
                $this->validationRules = [];
                $this->authorizationRequirements = [];
                $this->businessRules = [];
                $this->observabilityRequirements = [];
                $this->auditRequirements = [];
                $this->initializeBusinessFunction();
            }

            protected function executeBusiness(array $request): array
            {
                $this->calls[] = $this->functionId;
                if ($this->failExecution) {
                    throw new \RuntimeException("{$this->functionId} failed");
                }
                return ['ok' => true, 'request' => $request];
            }
        };

        $bf->setAuthorizer(new class ($caller) implements AuthorizerInterface {
            public function __construct(private string $allowed)
            {
            }

            public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
            {
                return $caller === $this->allowed
                    ? AuthorizationDecision::allow()
                    : AuthorizationDecision::deny('caller not allowed');
            }
        });

        return $bf;
    }

    /**
     * @param array<WorkflowStep> $steps
     * @param array<BusinessFunction> $functions
     */
    private function run1(array $steps, array $functions, array $metadata = ['caller' => 'wf-user']): WorkflowResult
    {
        $registry = new WorkflowRegistry();
        $registry->register(new WorkflowDefinition(id: 'wf', name: 'wf', displayName: 'wf', moduleId: 'T', steps: $steps));
        $engine = new WorkflowEngine($registry);
        foreach ($functions as $f) {
            $engine->registerBusinessFunction($f);
        }

        return $engine->execute('wf', new WorkflowContext('wf', 'exec-1', 'corr-1', inputData: ['k' => 'v'], metadata: $metadata));
    }

    public function testSequentialWorkflowExecutesAllSteps(): void
    {
        $result = $this->run1(
            [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s2', 'B', 2), new WorkflowStep('s3', 'C', 3)],
            [$this->fn('A'), $this->fn('B'), $this->fn('C')]
        );

        $this->assertTrue($result->succeeded());
        $this->assertSame(['A', 'B', 'C'], $this->calls);
        $this->assertSame(['s1', 's2', 's3'], $result->completedSteps);
    }

    public function testUnorderedStepsExecuteBySequenceNotArrayOrder(): void
    {
        // Declared [1, 3, 2]: previously executed A, C and skipped B while reporting success.
        $result = $this->run1(
            [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s3', 'C', 3), new WorkflowStep('s2', 'B', 2)],
            [$this->fn('A'), $this->fn('B'), $this->fn('C')]
        );

        $this->assertTrue($result->succeeded());
        $this->assertSame(['A', 'B', 'C'], $this->calls);
        $this->assertSame(['s1', 's2', 's3'], $result->completedSteps);
    }

    public function testReverseDeclaredStepsExecuteBySequence(): void
    {
        $result = $this->run1(
            [new WorkflowStep('s3', 'C', 30), new WorkflowStep('s2', 'B', 20), new WorkflowStep('s1', 'A', 10)],
            [$this->fn('A'), $this->fn('B'), $this->fn('C')]
        );

        $this->assertTrue($result->succeeded());
        $this->assertSame(['A', 'B', 'C'], $this->calls);
    }

    /**
     * D8 (approved 2026-11-01): duplicate sequences are rejected when the
     * workflow definition is constructed, before registration or execution.
     */
    public function testDuplicateSequenceIsRejectedAtDefinitionConstruction(): void
    {
        try {
            new WorkflowDefinition(
                id: 'wf',
                name: 'wf',
                displayName: 'wf',
                steps: [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s2', 'B', 1)]
            );
            $this->fail('Expected WorkflowDefinitionException');
        } catch (WorkflowDefinitionException $e) {
            $this->assertSame(WorkflowDefinitionException::REASON_DUPLICATE_SEQUENCE, $e->getReasonCode());
            $this->assertStringContainsString('duplicate sequence 1', $e->getMessage());
        }
        $this->assertSame([], $this->calls);
    }

    /**
     * D8: no implicit default sequence of 0. An omitted sequence is rejected
     * at step construction with a clear WBF error.
     */
    public function testMissingSequenceIsRejectedAtStepConstruction(): void
    {
        try {
            new WorkflowStep('s1', 'A');
            $this->fail('Expected WorkflowDefinitionException');
        } catch (WorkflowDefinitionException $e) {
            $this->assertSame(WorkflowDefinitionException::REASON_MISSING_SEQUENCE, $e->getReasonCode());
            $this->assertSame("Workflow step 's1': sequence is required", $e->getMessage());
        }

        $this->expectException(WorkflowDefinitionException::class);
        new WorkflowStep('s2', 'B', null);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonIntegerSequences(): array
    {
        return [
            'float' => [1.5],
            'integral float' => [2.0],
            'numeric string' => ['1'],
            'boolean' => [true],
            'array' => [[1]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonIntegerSequences')]
    public function testNonIntegerSequenceIsRejectedAtStepConstruction(mixed $sequence): void
    {
        try {
            new WorkflowStep('s1', 'A', $sequence);
            $this->fail('Expected WorkflowDefinitionException');
        } catch (WorkflowDefinitionException $e) {
            $this->assertSame(WorkflowDefinitionException::REASON_INVALID_SEQUENCE, $e->getReasonCode());
        }
    }

    public function testSequenceZeroIsValid(): void
    {
        $result = $this->run1(
            [new WorkflowStep('s1', 'B', 1), new WorkflowStep('s0', 'A', 0)],
            [$this->fn('A'), $this->fn('B')]
        );

        $this->assertTrue($result->succeeded());
        $this->assertSame(['A', 'B'], $this->calls);
    }

    public function testWorkflowDefinitionExceptionIsAnInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowStep('s1', 'A');
    }

    public function testDuplicateStepIdIsRejectedAtRegistration(): void
    {
        // Existing WorkflowRegistry behavior (preserved).
        $this->expectException(\WaysNX\BusinessFramework\Exceptions\DuplicateWorkflowException::class);

        $this->run1(
            [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s1', 'B', 2)],
            [$this->fn('A'), $this->fn('B')]
        );
    }

    public function testDuplicateStepIdFailsWhenDefinitionBypassesRegistry(): void
    {
        $engine = new WorkflowEngine(new WorkflowRegistry());
        $engine->registerBusinessFunction($this->fn('A'))->registerBusinessFunction($this->fn('B'));

        $result = $engine->executeWorkflow(
            new WorkflowDefinition(
                id: 'wf',
                name: 'wf',
                displayName: 'wf',
                steps: [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s1', 'B', 2)]
            ),
            new WorkflowContext('wf', 'exec-1', metadata: ['caller' => 'wf-user'])
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame([], $this->calls);
        $this->assertStringContainsString("duplicate step id 's1'", implode(' ', $result->errors));
    }

    public function testNegativeSequenceIsRejectedAtStepConstruction(): void
    {
        try {
            new WorkflowStep('s1', 'A', -1);
            $this->fail('Expected WorkflowDefinitionException');
        } catch (WorkflowDefinitionException $e) {
            $this->assertSame(WorkflowDefinitionException::REASON_NEGATIVE_SEQUENCE, $e->getReasonCode());
        }
    }

    public function testFailedStepStopsWorkflowAndIsReported(): void
    {
        $result = $this->run1(
            [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s2', 'B', 2), new WorkflowStep('s3', 'C', 3)],
            [$this->fn('A'), $this->fn('B', fail: true), $this->fn('C')]
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame(['A', 'B'], $this->calls);
        $this->assertSame(['s1'], $result->completedSteps);
        $this->assertSame(['s2'], $result->failedSteps);
        // Default reporter: the raw detail is logged server-side, not exposed.
        $this->assertStringContainsString('B failed', (string) file_get_contents($this->errorLog));
        $this->assertStringNotContainsString('B failed', implode(' ', $result->errors));
    }

    /**
     * ADR-0009 amendment (BF-ERR-03): a step failing with an unexpected
     * exception records the safe public message in the WorkflowResult, not
     * the raw exception message; the detail is reported server-side.
     */
    public function testUnexpectedStepFailureDoesNotExposeTheRawMessage(): void
    {
        $reported = [];
        $registry = new WorkflowRegistry();
        $registry->register(new WorkflowDefinition(id: 'wf', name: 'wf', displayName: 'wf', moduleId: 'T', steps: [new WorkflowStep('s1', 'B', 1)]));
        $engine = (new WorkflowEngine($registry))
            ->registerBusinessFunction($this->fn('B', fail: true))
            ->setBusinessFunctionRuntime(new BusinessFunctionRuntime(function (string $m, array $c) use (&$reported): void {
                $reported[] = $c;
            }));

        $result = $engine->execute('wf', new WorkflowContext('wf', 'exec-1', 'corr-1', inputData: [], metadata: ['caller' => 'wf-user']));
        $errors = implode(' ', $result->errors);

        $this->assertSame('failed', $result->status);
        $this->assertStringNotContainsString('B failed', $errors);
        $this->assertStringContainsString(ExecutionResult::SAFE_MESSAGE, $errors);
        $this->assertStringContainsString("Step 's1' failed", $errors);
        $this->assertCount(1, $reported);
        $this->assertSame('B failed', $reported[0]['exception']->getMessage());
    }

    public function testExpectedStepFailureKeepsTheUserFacingMessage(): void
    {
        $result = $this->run1([new WorkflowStep('s1', 'A', 1)], [$this->fn('A', caller: 'someone-else')]);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('authorization_failure', implode(' ', $result->errors));
    }

    public function testUnregisteredBusinessFunctionFailsInsteadOfSilentSuccess(): void
    {
        $result = $this->run1([new WorkflowStep('s1', 'MISSING', 1)], []);

        $this->assertSame('failed', $result->status);
        $this->assertSame(['s1'], $result->failedSteps);
        $this->assertStringContainsString("No business function registered for 'MISSING'", implode(' ', $result->errors));
    }

    public function testStepInvokesBusinessFunctionThroughPipelineIncludingAuthorization(): void
    {
        // Caller is not allowed by the step function's authorizer: the step must fail.
        $result = $this->run1(
            [new WorkflowStep('s1', 'A', 1)],
            [$this->fn('A')],
            ['caller' => 'someone-else']
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame([], $this->calls, 'Business logic must not run when authorization is denied');
        $this->assertStringContainsString('caller not allowed', implode(' ', $result->errors));
    }

    public function testStepWithoutCallerContextFailsClosed(): void
    {
        $result = $this->run1([new WorkflowStep('s1', 'A', 1)], [$this->fn('A')], []);

        $this->assertSame('failed', $result->status);
        $this->assertSame([], $this->calls);
    }

    public function testEmptyWorkflowStillCompletes(): void
    {
        $result = $this->run1([], []);

        $this->assertTrue($result->succeeded());
    }

    public function testCustomTransitionExtensionPointIsPreserved(): void
    {
        // A subclass that intentionally branches (skips s2) keeps working:
        // the "all steps executed" check only applies to default transitions.
        $registry = new WorkflowRegistry();
        $registry->register(new WorkflowDefinition(
            id: 'wf',
            name: 'wf',
            displayName: 'wf',
            steps: [new WorkflowStep('s1', 'A', 1), new WorkflowStep('s2', 'B', 2), new WorkflowStep('s3', 'C', 3)]
        ));

        $engine = new class ($registry) extends WorkflowEngine {
            public array $ran = [];

            protected function executeBusinessFunction(string $functionId, WorkflowContext $context): void
            {
                $this->ran[] = $functionId;
            }

            protected function determineNextStep(WorkflowDefinition $workflow, WorkflowStep $currentStep, array $completedSteps): ?string
            {
                return $currentStep->id === 's1' ? 's3' : null;
            }
        };

        $result = $engine->execute('wf', new WorkflowContext('wf', 'exec-1'));

        $this->assertTrue($result->succeeded());
        $this->assertSame(['A', 'C'], $engine->ran);
    }
}
