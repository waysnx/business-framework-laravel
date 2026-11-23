<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\Authorization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\Authorization\AuthorizationDecision;
use WaysNX\BusinessFramework\Contracts\AuthorizerInterface;
use WaysNX\BusinessFramework\Exceptions\AuthorizationException;
use WaysNX\BusinessFramework\Models\BusinessFunction;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;

/**
 * Regression tests for LAR-08: BusinessFunctionAbstract::checkAuthorization()
 * previously allowed every execution by default.
 *
 * Contract under test (Phase 0 decision D6): authorization fails closed and
 * requires an explicit, valid decision. Caller-supplied values are never
 * proof of authorization on their own.
 */
final class AuthorizationFailClosedTest extends TestCase
{
    /**
     * Minimal BF that records whether its business logic ran.
     */
    private function makeFunction(): BusinessFunction
    {
        return new class extends BusinessFunction {
            public bool $executed = false;

            public function __construct()
            {
                $this->functionId = 'T.AUTH.CHECK.RUN';
                $this->functionName = 'Run';
                $this->functionVersion = '1.0.0';
                $this->module = 'T';
                $this->domain = 'AUTH';
                $this->capability = 'CHECK';
                $this->lifecycleStatus = 'Draft';
                $this->description = 'Authorization test double';
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
                $this->responseContract = ['fields' => ['ok' => ['type' => 'boolean']]];
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
                $this->executed = true;
                return ['ok' => true];
            }
        };
    }

    private function authorizerReturning(mixed $value): AuthorizerInterface
    {
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

    public function testExplicitAllowExecutes(): void
    {
        $bf = $this->makeFunction()->setAuthorizer($this->authorizerReturning(AuthorizationDecision::allow()));

        $response = (new BusinessFunctionRuntime())->execute($bf, [], 'user-1');

        $this->assertSame(['ok' => true], $response);
        $this->assertTrue($bf->executed);
    }

    public function testExplicitDenyBlocksExecution(): void
    {
        $bf = $this->makeFunction()->setAuthorizer(
            $this->authorizerReturning(AuthorizationDecision::deny('Role lacks permission'))
        );

        try {
            (new BusinessFunctionRuntime())->execute($bf, [], 'user-1');
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame(AuthorizationException::REASON_DENIED, $e->getReasonCode());
            $this->assertSame('AuthorizationError', $e->getCategory());
            $this->assertStringContainsString('Role lacks permission', $e->getMessage());
        }

        $this->assertFalse($bf->executed);
    }

    public function testMissingAuthorizerFailsClosed(): void
    {
        $bf = $this->makeFunction();

        try {
            (new BusinessFunctionRuntime())->execute($bf, [], 'user-1');
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame(AuthorizationException::REASON_NO_AUTHORIZER, $e->getReasonCode());
        }

        $this->assertFalse($bf->executed);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidDecisions(): array
    {
        return [
            'boolean true' => [true],
            'null' => [null],
            'array claiming allowed' => [['allowed' => true]],
            'string' => ['allow'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function testInvalidDecisionFailsClosed(mixed $decision): void
    {
        $bf = $this->makeFunction()->setAuthorizer($this->authorizerReturning($decision));

        try {
            (new BusinessFunctionRuntime())->execute($bf, [], 'user-1');
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame(AuthorizationException::REASON_INVALID_DECISION, $e->getReasonCode());
        }

        $this->assertFalse($bf->executed);
    }

    public function testCallerSuppliedClaimsAreNotProofOfAuthorization(): void
    {
        $bf = $this->makeFunction();

        $this->expectException(AuthorizationException::class);

        (new BusinessFunctionRuntime())->execute(
            $bf,
            ['authorized' => true],
            ['id' => 'user-1', 'authorized' => true, 'roles' => ['admin']]
        );
    }

    public function testAuthorizationExceptionRemainsARuntimeException(): void
    {
        // Backward compatibility: execute() documents \RuntimeException for authorization failures.
        $this->expectException(\RuntimeException::class);

        (new BusinessFunctionRuntime())->execute($this->makeFunction(), []);
    }

    public function testAuthorizerReceivesFunctionRequestAndCaller(): void
    {
        $seen = [];
        $authorizer = new class ($seen) implements AuthorizerInterface {
            public function __construct(private array &$seen)
            {
            }

            public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller)
            {
                $this->seen = [$function->getFunctionId(), $request, $caller];
                return AuthorizationDecision::allow();
            }
        };

        $bf = $this->makeFunction()->setAuthorizer($authorizer);
        (new BusinessFunctionRuntime())->execute($bf, ['x' => 1], 'user-9');

        $this->assertSame(['T.AUTH.CHECK.RUN', ['x' => 1], 'user-9'], $seen);
    }

    public function testValidationRunsBeforeAuthorization(): void
    {
        $bf = new class extends BusinessFunction {
            public function __construct()
            {
                $this->functionId = 'T.AUTH.CHECK.VALIDATE_FIRST';
                $this->functionName = 'ValidateFirst';
                $this->functionVersion = '1.0.0';
                $this->module = 'T';
                $this->domain = 'AUTH';
                $this->capability = 'CHECK';
                $this->lifecycleStatus = 'Draft';
                $this->description = 'Validation precedes authorization';
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

            protected function validateRequest(array $request): void
            {
                throw new \InvalidArgumentException('invalid request');
            }
        };

        // No authorizer configured: the validation failure must surface first.
        $this->expectException(\InvalidArgumentException::class);
        (new BusinessFunctionRuntime())->execute($bf, []);
    }
}
