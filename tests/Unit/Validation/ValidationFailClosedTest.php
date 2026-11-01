<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Registry\ValidationDefinition;
use WaysNX\BusinessFramework\Registry\ValidationRegistry;
use WaysNX\BusinessFramework\Registry\ValidationRuleDefinition;
use WaysNX\BusinessFramework\Validation\ValidationContext;
use WaysNX\BusinessFramework\Validation\ValidationFramework;
use WaysNX\BusinessFramework\Validation\ValidationResult;

/**
 * Regression tests for LAR-01: ValidationFramework::evaluateRule() previously
 * returned true for every rule, so validation passed for empty input.
 *
 * Contract under test (Phase 0 decision D2):
 * - built-in 'required' rejects missing, null, whitespace-only and empty arrays
 * - unknown / unimplemented rule types fail closed (WBF.VALIDATION.UNKNOWN_RULE)
 * - warnings never block on their own
 * - all failing rules are reported
 */
final class ValidationFailClosedTest extends TestCase
{
    private function run1(array $rules, array $input): ValidationResult
    {
        $registry = new ValidationRegistry();
        $registry->register(new ValidationDefinition(
            id: 'v',
            name: 'V',
            displayName: 'V',
            rules: $rules
        ));

        return (new ValidationFramework($registry))
            ->execute('v', new ValidationContext(inputData: $input));
    }

    private function required(string $id, string $target, string $severity = 'error'): ValidationRuleDefinition
    {
        return new ValidationRuleDefinition(
            id: $id,
            name: $id,
            ruleType: 'required',
            target: $target,
            severity: $severity
        );
    }

    public function testRequiredFieldPresentPasses(): void
    {
        $result = $this->run1([$this->required('r1', 'name')], ['name' => 'Alice']);

        $this->assertTrue($result->passed);
        $this->assertSame(0, $result->issueCount());
    }

    /**
     * @return array<string, array{0: array}>
     */
    public static function missingOrEmptyInputs(): array
    {
        return [
            'missing key' => [[]],
            'null' => [['name' => null]],
            'empty string' => [['name' => '']],
            'whitespace only' => [['name' => "  \t"]],
            'empty array' => [['name' => []]],
        ];
    }

    #[DataProvider('missingOrEmptyInputs')]
    public function testRequiredFieldMissingOrEmptyFails(array $input): void
    {
        $result = $this->run1([$this->required('r1', 'name')], $input);

        $this->assertFalse($result->passed);
        $this->assertCount(1, $result->errors());
        $issue = $result->errors()[0];
        $this->assertSame('r1', $issue->ruleId);
        $this->assertSame('name', $issue->target);
        $this->assertSame(ValidationFramework::CODE_REQUIRED, $issue->errorCode);
        $this->assertSame("Field 'name' is required", $issue->message);
    }

    public function testRequiredKeepsFalsyButPresentValues(): void
    {
        $result = $this->run1(
            [$this->required('r1', 'count'), $this->required('r2', 'flag')],
            ['count' => 0, 'flag' => false]
        );

        $this->assertTrue($result->passed, '0 and false are present values');
    }

    public function testRequiredResolvesDotPaths(): void
    {
        $rules = [$this->required('r1', 'address.city')];

        $this->assertTrue($this->run1($rules, ['address' => ['city' => 'Pune']])->passed);
        $this->assertFalse($this->run1($rules, ['address' => []])->passed);
    }

    public function testRuleSpecificErrorCodeAndMessageArePreserved(): void
    {
        $rule = new ValidationRuleDefinition(
            id: 'r1',
            name: 'r1',
            ruleType: 'required',
            target: 'email',
            message: 'Email is required',
            errorCode: 'VAL_EMAIL_001'
        );

        $issue = $this->run1([$rule], [])->errors()[0];

        $this->assertSame('VAL_EMAIL_001', $issue->errorCode);
        $this->assertSame('Email is required', $issue->message);
    }

    public function testUnknownRuleTypeFailsClosed(): void
    {
        $rule = new ValidationRuleDefinition(id: 'r1', name: 'r1', ruleType: 'no-such-rule', target: 'name');

        $result = $this->run1([$rule], ['name' => 'Alice']);

        $this->assertFalse($result->passed);
        $this->assertSame(ValidationFramework::CODE_UNKNOWN_RULE, $result->errors()[0]->errorCode);
    }

    public function testDefaultCustomRuleTypeIsNotSilentlyPassed(): void
    {
        // ValidationRuleDefinition defaults ruleType to 'custom'; without an
        // application implementation it must not pass.
        $rule = new ValidationRuleDefinition(id: 'r1', name: 'r1');

        $result = $this->run1([$rule], ['anything' => 'x']);

        $this->assertFalse($result->passed);
        $this->assertSame(ValidationFramework::CODE_UNKNOWN_RULE, $result->errors()[0]->errorCode);
    }

    public function testUnknownRuleDeclaredAsWarningStillFailsClosedAsError(): void
    {
        $rule = new ValidationRuleDefinition(
            id: 'r1',
            name: 'r1',
            ruleType: 'no-such-rule',
            target: 'name',
            severity: 'warning'
        );

        $result = $this->run1([$rule], ['name' => 'Alice']);

        $this->assertFalse($result->passed);
        $this->assertSame('error', $result->issues[0]->severity);
    }

    public function testRequiredRuleWithoutTargetFailsClosed(): void
    {
        $result = $this->run1([$this->required('r1', '')], ['name' => 'Alice']);

        $this->assertFalse($result->passed);
        $this->assertSame(ValidationFramework::CODE_INVALID_RULE, $result->errors()[0]->errorCode);
    }

    public function testValidInputWithWarningPasses(): void
    {
        $result = $this->run1(
            [$this->required('r1', 'name'), $this->required('w1', 'nickname', 'warning')],
            ['name' => 'Alice']
        );

        $this->assertTrue($result->passed, 'A warning alone must not block');
        $this->assertCount(0, $result->errors());
        $this->assertCount(1, $result->warnings());
        $this->assertSame('w1', $result->warnings()[0]->ruleId);
    }

    public function testInvalidInputWithWarningFailsAndReportsBoth(): void
    {
        $result = $this->run1(
            [$this->required('r1', 'name'), $this->required('w1', 'nickname', 'warning')],
            []
        );

        $this->assertFalse($result->passed);
        $this->assertCount(1, $result->errors());
        $this->assertCount(1, $result->warnings());
    }

    public function testMultipleValidationErrorsAreAllReported(): void
    {
        $result = $this->run1(
            [$this->required('r1', 'name'), $this->required('r2', 'email')],
            []
        );

        $this->assertFalse($result->passed);
        $this->assertSame(['r1', 'r2'], array_map(fn($i) => $i->ruleId, $result->errors()));
    }

    public function testApplicationExtensionCanImplementCustomRulesAndDelegateBuiltIns(): void
    {
        $registry = new ValidationRegistry();
        $registry->register(new ValidationDefinition(
            id: 'v',
            name: 'V',
            displayName: 'V',
            rules: [
                new ValidationRuleDefinition(id: 'c1', name: 'c1', ruleType: 'custom', target: 'age'),
                $this->required('r1', 'name'),
            ]
        ));

        $framework = new class ($registry) extends ValidationFramework {
            protected function evaluateRule(ValidationRuleDefinition $rule, ValidationContext $context): bool
            {
                if ($rule->ruleType === 'custom') {
                    return ($context->inputData[$rule->target] ?? 0) >= 18;
                }

                return parent::evaluateRule($rule, $context);
            }
        };

        $this->assertTrue($framework->execute('v', new ValidationContext(inputData: ['age' => 20, 'name' => 'A']))->passed);

        $failed = $framework->execute('v', new ValidationContext(inputData: ['age' => 10]));
        $this->assertSame(['c1', 'r1'], array_map(fn($i) => $i->ruleId, $failed->errors()));
    }
}
