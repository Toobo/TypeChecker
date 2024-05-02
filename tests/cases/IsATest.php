<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use PHPUnit\Framework\Reorderable;
use PHPUnit\Framework\SelfDescribing;
use Toobo\TypeChecker\Type;

class IsATest extends TestCase
{
    /**
     * @return void
     */
    public function testUnaryBool(): void
    {
        self::assertTrue(Type::true()->isA('bool'));
        self::assertTrue(Type::false()->isA('bool'));
        self::assertTrue(Type::true()->isA('true'));
        self::assertTrue(Type::false()->isA('false'));
        self::assertFalse(Type::true()->isA('false'));
        self::assertFalse(Type::false()->isA('true'));
    }

    /**
     * @return void
     */
    public function testNull(): void
    {
        self::assertFalse(Type::mixed()->isA('null'));
        self::assertTrue(Type::null()->isA('mixed'));
        self::assertTrue(Type::null()->isA('null'));
        self::assertTrue(Type::null()->isNullable());
        self::assertTrue(Type::mixed()->isNullable());
    }

    /**
     * @return void
     */
    public function testNullable(): void
    {
        self::assertTrue(Type::byString('string|null')->isA('?string'));
        self::assertFalse(Type::byString('string|null')->isA('string'));
        self::assertTrue(Type::byString('string')->isA('?string'));
        self::assertTrue(Type::byString('null')->isA('?string'));
        self::assertTrue(Type::byString('string|null')->isNullable());
        self::assertTrue(Type::byString('?string')->isNullable());
        self::assertFalse(Type::byString('string')->isNullable());
    }

    /**
     * @return void
     */
    public function testIntersectingTypes(): void
    {
        self::assertFalse(Type::byString('string|null')->isA('string|int'));
        self::assertTrue(Type::byString('string')->isA('string|int'));
        self::assertTrue(Type::byString('int')->isA('string|int'));
        self::assertFalse(Type::byString('string|int')->isA('string|null'));
    }

    /**
     * @return void
     */
    public function testObjectMatchedByIntersectionTypes(): void
    {
        $arrayObject = Type::byString(\ArrayObject::class);
        $iteratorAndCountable = Type::byString('IteratorAggregate&Countable');

        self::assertTrue($iteratorAndCountable->matchedBy((string) $arrayObject));
        self::assertTrue($iteratorAndCountable->matchedBy($arrayObject));
        self::assertFalse($arrayObject->matchedBy((string) $iteratorAndCountable));
        self::assertFalse($arrayObject->matchedBy($iteratorAndCountable));

        self::assertTrue($arrayObject->isA($iteratorAndCountable));
        self::assertTrue($arrayObject->isA((string) $iteratorAndCountable));
        self::assertFalse($iteratorAndCountable->isA($arrayObject));
        self::assertFalse($iteratorAndCountable->isA((string) $arrayObject));
    }

    /**
     * @return void
     */
    public function testAllObjectsIsObject(): void
    {
        $allObjects = Type::byString(__CLASS__ . '|ArrayObject|(Iterator&Countable)');
        $notAllObjects = Type::byString(__CLASS__ . '|ArrayObject|iterable');

        self::assertTrue($allObjects->isA('object'));
        self::assertFalse($notAllObjects->isA('object'));
    }

    /**
     * @test
     */
    public function testDnfType(): void
    {
        $this->requireDnfTypes();

        $func = static fn (
            (\ArrayAccess & \Countable)
            | (Reorderable & SelfDescribing)
            | null $param
        ): int => 0;

        $left = Type::byReflectionType($this->extractTypeFromCallback($func));

        $right1 = Type::byString(__CLASS__);
        $right2 = Type::byString(TestCase::class);
        $right3 = Type::byString(\ArrayObject::class);
        /** @var Type $right4 */
        $right4 = Type::null();
        /** @var Type $right5 */
        $right5 = Type::int();
        /** @var Type $right6 */
        $right6 = Type::void();
        /** @var Type $right7 */
        $right7 = Type::never();
        /** @var Type $right8 */
        $right8 = Type::resource();
        $right9 = Type::byString('Meh');
        $right10 = Type::byString(\SplObjectStorage::class);

        static::assertFalse($left->isA($right1));
        static::assertFalse($left->isA($right2));
        static::assertFalse($left->isA($right3));
        static::assertFalse($left->isA($right4));
        static::assertFalse($left->isA($right10));

        static::assertTrue($left->isNullable());

        static::assertTrue($right1->isA($left));
        static::assertTrue($right2->isA($left));
        static::assertTrue($right3->isA($left));
        static::assertTrue($right4->isA($left));
        static::assertFalse($right5->isA($left));
        static::assertFalse($right6->isA($left));
        static::assertFalse($right7->isA($left));
        static::assertFalse($right8->isA($left));
        static::assertFalse($right9->isA($left));
        static::assertTrue($right10->isA($left));
    }
}
