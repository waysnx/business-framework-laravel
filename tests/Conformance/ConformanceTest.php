<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Conformance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Authorization\AuthorizationDecision;
use WaysNX\BusinessFramework\Contracts\AuthorizerInterface;
use WaysNX\BusinessFramework\Exceptions\BusinessRuleViolationException;
use WaysNX\BusinessFramework\Exceptions\ResourceNotFoundException;
use WaysNX\BusinessFramework\Exceptions\DuplicateWorkflowException;
use WaysNX\BusinessFramework\Exceptions\WorkflowDefinitionException;
use WaysNX\BusinessFramework\Lifecycle\LifecycleContext;
use WaysNX\BusinessFramework\Lifecycle\LifecycleEvent;
use WaysNX\BusinessFramework\Lifecycle\LifecycleHandler;
use WaysNX\BusinessFramework\Lifecycle\LifecycleManager;
use WaysNX\BusinessFramework\Models\BusinessFunction;
use WaysNX\BusinessFramework\Registry\ValidationDefinition;
use WaysNX\BusinessFramework\Registry\ValidationRegistry;
use WaysNX\BusinessFramework\Registry\ValidationRuleDefinition;
use WaysNX\BusinessFramework\Registry\WorkflowDefinition;
use WaysNX\BusinessFramework\Registry\WorkflowRegistry;
use WaysNX\BusinessFramework\Registry\WorkflowStep;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;
use WaysNX\BusinessFramework\Validation\ValidationContext;
use WaysNX\BusinessFramework\Validation\ValidationFramework;
use WaysNX\BusinessFramework\Workflow\WorkflowContext;
use WaysNX\BusinessFramework\Workflow\WorkflowEngine;

/**
 * WBF cross-runtime conformance adapter (Laravel / PHP).
 *
 * Executes the technology-independent fixtures owned by the Core repository
 * (waysnx-business-framework/tests/conformance/fixtures) against this runtime
 * and compares NORMALIZED business outcomes with the fixture expectations.
 *
 * - Fixture location: $WBF_CONFORMANCE_FIXTURES, or the sibling checkout
 *   ../waysnx-business-framework/tests/conformance/fixtures. When neither
 *   exists the tests are skipped locally. With WBF_CONFORMANCE_REQUIRED=1
 *   (CI) a missing checkout is a CONFIGURATION ERROR failure instead.
 * - Suite integrity: suite.json (manifest) and fixture.schema.json are
 *   checked by testSuiteIntegrity() before any fixture is trusted.
 * - Known differences: conformance-known-differences.json in this directory.
 *   A listed fixture that fails is reported as incomplete; a listed fixture
 *   that passes FAILS, so the list must be kept accurate.
 * - Report: set $WBF_CONFORMANCE_REPORT to a file path to write a JSON report.
 *
 * Normalization rules: CROSS_RUNTIME_CONFORMANCE.md §6. No other
 * normalization is permitted.
 */
final class ConformanceTest extends TestCase
{
    /** Contracts this adapter has drivers for. */
    private const DRIVERS = ['validation', 'workflow', 'business-function', 'authorization', 'lifecycle'];

    /** @var array<int, array<string, mixed>> */
    private static array $report = [];

    public static function fixtureDir(): string
    {
        $env = getenv('WBF_CONFORMANCE_FIXTURES');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return dirname(__DIR__, 3) . '/waysnx-business-framework/tests/conformance/fixtures';
    }

    /** suite.json and fixture.schema.json live one level above fixtures/. */
    public static function suiteDir(): string
    {
        return dirname(self::fixtureDir());
    }

    /** CI sets WBF_CONFORMANCE_REQUIRED=1: missing fixtures are a configuration failure, never a skip. */
    private static function required(): bool
    {
        return getenv('WBF_CONFORMANCE_REQUIRED') === '1';
    }

    private static function available(): bool
    {
        return is_dir(self::fixtureDir()) && is_file(self::suiteDir() . '/suite.json');
    }

    private static function unavailableMessage(): string
    {
        return 'WBF conformance fixtures not found at ' . self::fixtureDir()
            . ' (expected suite manifest ' . self::suiteDir() . '/suite.json). '
            . 'Set WBF_CONFORMANCE_FIXTURES to <core>/tests/conformance/fixtures.';
    }

    private static function readJson(string $file): array
    {
        return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{0: string, 1: array|null}>
     */
    public static function fixtures(): array
    {
        if (!self::available()) {
            return ['fixtures-unavailable' => ['', null]];
        }

        $suite = self::readJson(self::suiteDir() . '/suite.json');
        $cases = [];
        foreach ($suite['contracts'] as $contract => $spec) {
            $file = self::suiteDir() . '/' . $spec['file'];
            if (!is_file($file)) {
                continue; // reported by testSuiteIntegrity()
            }
            foreach (self::readJson($file)['fixtures'] ?? [] as $fixture) {
                $cases[($fixture['id'] ?? '?') . ' ' . ($fixture['description'] ?? '')] = [$contract, $fixture];
            }
        }

        return $cases;
    }

    /**
     * Suite integrity (suite.json + fixture.schema.json rules): every listed
     * file exists with the manifest suiteVersion and contract, fixture IDs are
     * valid, unique and exactly the manifest list, required properties exist,
     * and expected keys / statuses are allowed for the contract. This makes the
     * Laravel and Node adapters provably execute the same fixture set.
     *
     * @return array<int, string>
     */
    public static function suiteProblems(array $suite): array
    {
        $problems = [];
        $seen = [];
        $count = 0;
        foreach ($suite['contracts'] as $contract => $spec) {
            $file = self::suiteDir() . '/' . $spec['file'];
            if (!in_array($contract, self::DRIVERS, true)) {
                $problems[] = "no Laravel driver for contract '{$contract}'";
            }
            if (!is_file($file)) {
                $problems[] = "{$contract}: missing fixture file {$spec['file']}";
                continue;
            }
            $data = self::readJson($file);
            if (($data['contract'] ?? null) !== $contract) {
                $problems[] = "{$spec['file']}: contract '" . ($data['contract'] ?? '') . "' != '{$contract}'";
            }
            if (($data['suiteVersion'] ?? null) !== $suite['suiteVersion']) {
                $problems[] = "{$spec['file']}: suiteVersion '" . ($data['suiteVersion'] ?? '') . "' != manifest '{$suite['suiteVersion']}'";
            }
            if (!is_string($data['description'] ?? null) || $data['description'] === '') {
                $problems[] = "{$spec['file']}: missing description";
            }
            if (!is_array($data['fixtures'] ?? null)) {
                $problems[] = "{$spec['file']}: 'fixtures' is not an array";
                continue;
            }
            $ids = [];
            foreach ($data['fixtures'] as $fx) {
                $count++;
                $id = $fx['id'] ?? '?';
                $where = "{$spec['file']}#{$id}";
                if (!is_string($fx['id'] ?? null) || !preg_match('/^[A-Z]+-\d{3}$/', $fx['id'])) {
                    $problems[] = "{$where}: invalid id";
                }
                if (isset($seen[$id])) {
                    $problems[] = "{$where}: duplicate fixture id";
                }
                $seen[$id] = true;
                $ids[] = $id;
                foreach (array_keys($fx) as $key) {
                    if (!in_array($key, ['id', 'description', 'input', 'expected', 'metadata'], true)) {
                        $problems[] = "{$where}: unknown property '{$key}'";
                    }
                }
                if (!is_string($fx['description'] ?? null) || $fx['description'] === '') {
                    $problems[] = "{$where}: missing description";
                }
                if (!is_array($fx['input'] ?? null)) {
                    $problems[] = "{$where}: missing input";
                }
                if (!is_array($fx['expected'] ?? null) || $fx['expected'] === []) {
                    $problems[] = "{$where}: missing or empty expected";
                    continue;
                }
                foreach (array_keys($fx['expected']) as $key) {
                    if (!in_array($key, $spec['expectedKeys'], true)) {
                        $problems[] = "{$where}: expected key '{$key}' not allowed for {$contract}";
                    }
                }
                if (array_key_exists('status', $fx['expected']) && !in_array($fx['expected']['status'], $spec['statuses'], true)) {
                    $problems[] = "{$where}: status '{$fx['expected']['status']}' not allowed for {$contract}";
                }
            }
            if ($ids !== $spec['fixtureIds']) {
                $problems[] = "{$spec['file']}: fixture ids " . json_encode($ids) . ' != manifest ' . json_encode($spec['fixtureIds']);
            }
        }
        if ($count !== $suite['fixtureCount']) {
            $problems[] = "fixture count {$count} != manifest fixtureCount {$suite['fixtureCount']}";
        }

        return $problems;
    }

    public function testSuiteIntegrity(): void
    {
        if (!self::available()) {
            if (self::required()) {
                $this->fail('CONFIGURATION ERROR: ' . self::unavailableMessage() . ' (WBF_CONFORMANCE_REQUIRED=1)');
            }
            $this->markTestSkipped(self::unavailableMessage() . ' Suite skipped (set WBF_CONFORMANCE_REQUIRED=1 to fail instead).');
        }

        $this->assertSame([], self::suiteProblems(self::readJson(self::suiteDir() . '/suite.json')));
    }

    #[DataProvider('fixtures')]
    public function testFixture(string $contract, ?array $fixture): void
    {
        if ($fixture === null) {
            if (self::required()) {
                $this->fail('CONFIGURATION ERROR: ' . self::unavailableMessage() . ' (WBF_CONFORMANCE_REQUIRED=1)');
            }
            $this->markTestSkipped(self::unavailableMessage());
        }

        $actual = match ($contract) {
            'validation' => $this->runValidation($fixture['input']),
            'workflow' => $this->runWorkflow($fixture['input']),
            'business-function', 'authorization' => $this->runBusinessFunction($fixture['input']),
            'lifecycle' => $this->runLifecycle($fixture['input']),
        };

        $projected = [];
        foreach (array_keys($fixture['expected']) as $key) {
            $projected[$key] = $actual[$key] ?? null;
        }

        $expected = self::canonical($fixture['expected']);
        $projected = self::canonical($projected);
        $passed = $expected === $projected;

        $known = self::knownDifferences()[$fixture['id']] ?? null;
        self::$report[] = array_filter([
            'id' => $fixture['id'],
            'contract' => $contract,
            'outcome' => $passed ? 'pass' : 'fail',
            'normalized' => $projected,
            'knownDifference' => $known,
        ], fn($v) => $v !== null);

        if ($known !== null) {
            if ($passed) {
                $this->fail("{$fixture['id']} now passes; remove it from conformance-known-differences.json");
            }
            $this->markTestIncomplete("Known difference ({$known}): " . json_encode($projected));
        }

        $this->assertSame($expected, $projected, "{$fixture['id']}: normalized outcome differs from Core expectation");
    }

    public static function tearDownAfterClass(): void
    {
        $target = getenv('WBF_CONFORMANCE_REPORT');
        if (!is_string($target) || $target === '' || self::$report === []) {
            return;
        }

        $suite = self::readJson(self::suiteDir() . '/suite.json');
        file_put_contents($target, json_encode([
            'runtime' => 'laravel',
            'suiteVersion' => $suite['suiteVersion'],
            'integrityProblems' => self::suiteProblems($suite),
            'total' => count(self::$report),
            'passed' => count(array_filter(self::$report, fn($r) => $r['outcome'] === 'pass')),
            'failed' => count(array_filter(self::$report, fn($r) => $r['outcome'] === 'fail')),
            'results' => self::$report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // ------------------------------------------------------------------
    // Drivers
    // ------------------------------------------------------------------

    private function runValidation(array $input): array
    {
        $rules = array_map(fn(array $r) => new ValidationRuleDefinition(
            id: $r['id'],
            name: $r['id'],
            ruleType: $r['type'],
            target: $r['field'] ?? '',
            message: $r['message'] ?? '',
            errorCode: $r['code'] ?? '',
            severity: $r['severity'] ?? 'error'
        ), $input['rules']);

        $framework = new ValidationFramework(new ValidationRegistry());
        $result = $framework->executeValidation(
            new ValidationDefinition(id: 'conformance', name: 'conformance', displayName: 'conformance', rules: $rules),
            new ValidationContext(inputData: $input['data'])
        );

        $map = fn(array $issues) => array_values(array_map(
            fn($i) => ['ruleId' => $i->ruleId, 'field' => $i->target, 'code' => $i->errorCode],
            $issues
        ));

        return [
            'status' => $result->passed ? 'passed' : 'failed',
            'errors' => $map($result->errors()),
            'warnings' => $map($result->warnings()),
        ];
    }

    private function authorizerFor(string $mode): ?AuthorizerInterface
    {
        $value = match ($mode) {
            'allow' => AuthorizationDecision::allow('fixture'),
            'deny' => AuthorizationDecision::deny('fixture deny'),
            'invalid' => true,
            'none' => null,
        };

        if ($mode === 'none') {
            return null;
        }

        return new class ($value) implements AuthorizerInterface {
            public function __construct(private mixed $value)
            {
            }

            public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
            {
                return $this->value;
            }
        };
    }

    private function runBusinessFunction(array $input): array
    {
        $state = (object) ['logicInvoked' => false];
        $bf = self::fixtureFunction($input['function'], $input['behavior'], $input['response'] ?? [], $state);
        $authorizer = $this->authorizerFor($input['authorization']);
        if ($authorizer !== null) {
            $bf->setAuthorizer($authorizer);
        }

        // §6.2 (suite 0.1.0): BusinessFunctionRuntime::run() returns the WBF
        // result status natively (WBF-SPEC-0006, D1). No status normalization
        // is applied; the adapter reads the result verbatim.
        // The error reporter is muted here only to keep test output clean; the
        // fixtures compare statuses, not server-side logging (covered by
        // tests/Unit/Runtime/ExecutionResultTest).
        $runtime = new BusinessFunctionRuntime(static function (string $message, array $context): void {
        });
        $result = $runtime->run($bf, $input['request'], $input['caller']);

        $reason = match (true) {
            $result->succeeded() => 'allowed',
            $result->status === 'authorization_failure' => $result->errorCode,
            default => null,
        };

        return [
            'status' => $result->status,
            'logicInvoked' => $state->logicInvoked,
            'response' => $result->response,
            'authorizationReason' => $reason,
        ];
    }

    private function runWorkflow(array $input): array
    {
        $order = [];
        $requests = [];

        // §6.4: a definition the runtime refuses to construct or register is
        // `definition_rejected` (WorkflowDefinitionException from step or
        // definition construction, DuplicateWorkflowException from registration).
        $registry = new WorkflowRegistry();
        try {
            $steps = array_map(
                fn(array $s) => array_key_exists('sequence', $s)
                    ? new WorkflowStep($s['id'], $s['function'], $s['sequence'])
                    : new WorkflowStep($s['id'], $s['function']),
                $input['steps']
            );
            $registry->register(new WorkflowDefinition(id: 'wf', name: 'wf', displayName: 'wf', moduleId: 'CONF', steps: $steps));
        } catch (WorkflowDefinitionException | DuplicateWorkflowException) {
            return ['status' => 'definition_rejected', 'executionOrder' => $order];
        }

        $engine = new WorkflowEngine($registry);
        // Muted reporter: see runBusinessFunction(). Keeps fixture runs out of
        // the machine's PHP error log.
        $engine->setBusinessFunctionRuntime(new BusinessFunctionRuntime(static function (string $message, array $context): void {
        }));
        foreach ($input['functions'] as $fnId => $spec) {
            $state = (object) ['logicInvoked' => false];
            $bf = self::fixtureFunction(
                ['id' => $fnId, 'module' => 'CONF', 'domain' => 'WF', 'capability' => 'STEP'],
                $spec['behavior'] === 'fail' ? 'throw' : 'succeed',
                [],
                $state,
                function (string $id, array $request) use (&$order, &$requests): void {
                    $order[] = $id;
                    $requests[$id] = $request;
                }
            );
            $allowed = $input['caller'];
            $bf->setAuthorizer(new class ($allowed) implements AuthorizerInterface {
                public function __construct(private string $allowed)
                {
                }

                public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
                {
                    return $caller === $this->allowed
                        ? AuthorizationDecision::allow()
                        : AuthorizationDecision::deny('caller');
                }
            });
            $engine->registerBusinessFunction($bf);
        }

        $result = $engine->execute('wf', new WorkflowContext(
            'wf',
            'conformance',
            'conformance',
            inputData: $input['data'],
            metadata: ['caller' => $input['caller']]
        ));

        return [
            'status' => $result->status,
            'executionOrder' => $order,
            'completedSteps' => array_values($result->completedSteps),
            'failedSteps' => array_values($result->failedSteps),
            'errors' => array_map([self::class, 'workflowErrorKind'], $result->errors),
            'functionRequests' => $requests,
        ];
    }

    private function runLifecycle(array $input): array
    {
        $fired = 0;
        $callable = function () use (&$fired): void {
            $fired++;
        };

        $handler = array_key_exists('enabled', $input['handler'])
            ? new LifecycleHandler($input['handler']['id'], $callable, $input['handler']['events'], 0, $input['handler']['enabled'])
            : new LifecycleHandler($input['handler']['id'], $callable, $input['handler']['events']);

        $manager = new LifecycleManager();
        $manager->register($handler);
        $manager->dispatch(new LifecycleEvent($input['dispatch']), new LifecycleContext(entityId: 'conformance'));

        return ['fired' => $fired];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * §6.4 workflow error message → error kind.
     */
    public static function workflowErrorKind(string $message): string
    {
        return match (true) {
            (bool) preg_match('/No business function (runtime )?registered/i', $message) => 'function_not_registered',
            (bool) preg_match('/duplicate sequence/i', $message) => 'duplicate_sequence',
            (bool) preg_match('/duplicate step id/i', $message) => 'duplicate_step_id',
            (bool) preg_match("/Step '[^']+' failed/i", $message) => 'step_failed',
            default => 'unclassified',
        };
    }

    /**
     * Fixture Business Function implementing the declarative behaviors.
     *
     * @param array{id: string, module: string, domain: string, capability: string} $identity
     */
    private static function fixtureFunction(
        array $identity,
        string $behavior,
        array $response,
        object $state,
        ?\Closure $onLogic = null
    ): BusinessFunction {
        return new class ($identity, $behavior, $response, $state, $onLogic) extends BusinessFunction {
            public function __construct(
                array $identity,
                private string $behavior,
                private array $fixtureResponse,
                private object $state,
                private ?\Closure $onLogic
            ) {
                $this->functionId = $identity['id'];
                $this->functionName = $identity['id'];
                $this->functionVersion = '1.0.0';
                $this->module = $identity['module'];
                $this->domain = $identity['domain'];
                $this->capability = $identity['capability'];
                $this->lifecycleStatus = 'Draft';
                $this->description = 'Conformance fixture function';
                $this->businessOwner = 'WBF Conformance';
                $this->technicalOwner = 'WBF Conformance';
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

            protected function evaluateBusinessRules(array $request): void
            {
                if ($this->behavior === 'rule_violation') {
                    throw new \RuntimeException('fixture rule violated');
                }
            }

            protected function executeBusiness(array $request): array
            {
                $this->state->logicInvoked = true;
                if ($this->onLogic !== null) {
                    ($this->onLogic)($this->functionId, $request);
                }
                // Declarative fixture behaviors raised by the business logic.
                // 'rule_violation' (above) is raised by the business-rules
                // phase with a PLAIN exception, exercising the phase fallback;
                // 'rule_violation_in_logic' uses the categorized exception.
                match ($this->behavior) {
                    'throw' => throw new \RuntimeException('fixture unexpected error'),
                    'resource_missing' => throw new ResourceNotFoundException('FixtureResource', 'R-1'),
                    'rule_violation_in_logic' => throw new BusinessRuleViolationException('fixture rule violated in logic'),
                    default => null,
                };

                return $this->fixtureResponse;
            }
        };
    }

    /**
     * Canonical form for comparison: associative arrays are key-sorted,
     * lists keep their order. Scalars are compared strictly.
     */
    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonical'], $value);
        }
        ksort($value);

        return array_map([self::class, 'canonical'], $value);
    }

    /**
     * @return array<string, string>
     */
    private static function knownDifferences(): array
    {
        static $known = null;
        if ($known === null) {
            $data = json_decode(
                (string) file_get_contents(__DIR__ . '/conformance-known-differences.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $known = $data['knownDifferences'] ?? [];
        }

        return $known;
    }
}
