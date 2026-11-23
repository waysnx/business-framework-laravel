<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BusinessFunctionAbstractTest
 *
 * Definition validation of Core\BusinessFunctionAbstract::initializeBusinessFunction()
 * (WBF-SPEC-0005 identity and contract requirements, BF-ID / BF-CON).
 *
 * Rewritten in Phase 0.1 from the legacy Core file
 * packages/core/tests/BusinessFunctionAbstractTest.php, which never ran: its
 * double declared toArray()/toJson() protected against public abstract
 * methods (fatal at class load) and it assigned protected properties from
 * outside the class. Only the behavior that no other Laravel test covers was
 * kept: rejection of incomplete definitions and initialization of the
 * inherited identity fields. Accessors and the canonical BFD shape are
 * covered by tests/Unit/Models/BusinessFunctionTest.php and
 * tests/Unit/Models/CanonicalBFDTest.php.
 */
class BusinessFunctionAbstractTest extends TestCase
{
    public function testCompleteDefinitionInitializes(): void
    {
        $bf = new ConcreteBusinessFunction();

        $this->assertSame('HR.LEAVE.APPLY.APPLY_LEAVE', $bf->getFunctionId());
        $this->assertSame('1.0.0', $bf->getFunctionVersion());
        $this->assertSame(['leave'], $bf->getKeywords());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function mandatoryFields(): array
    {
        $fields = [
            'functionId', 'functionName', 'functionVersion', 'module', 'domain', 'capability',
            'lifecycleStatus', 'description', 'businessOwner', 'technicalOwner', 'classification', 'visibility',
        ];

        return array_combine($fields, array_map(static fn (string $f): array => [$f], $fields));
    }

    #[DataProvider('mandatoryFields')]
    public function testMissingMandatoryFieldIsRejected(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Business Function definition is incomplete: required field '{$field}' is missing");

        new ConcreteBusinessFunction([$field => null]);
    }

    #[DataProvider('mandatoryFields')]
    public function testWhitespaceOnlyMandatoryFieldIsRejected(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("required field '{$field}' is missing");

        new ConcreteBusinessFunction([$field => '   ']);
    }

    public function testEmptyRequestContractIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business Function definition is incomplete: requestContract is empty');

        new ConcreteBusinessFunction(['requestContract' => []]);
    }

    public function testEmptyResponseContractIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business Function definition is incomplete: responseContract is empty');

        new ConcreteBusinessFunction(['responseContract' => []]);
    }

    public function testInitializationSetsInheritedIdentityFields(): void
    {
        $bf = new ConcreteBusinessFunction();

        $this->assertStringStartsWith('test-uuid-', (string) $bf->getEntityId());
        $this->assertSame(ConcreteBusinessFunction::class, $bf->getEntityType());
        $this->assertSame(1, $bf->getEntityVersion());
        $this->assertNotNull($bf->getCreatedAt());
    }

    public function testEachInstanceGetsItsOwnEntityId(): void
    {
        $this->assertNotSame(
            (new ConcreteBusinessFunction())->getEntityId(),
            (new ConcreteBusinessFunction())->getEntityId()
        );
    }
}
