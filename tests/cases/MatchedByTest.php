<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use PHPUnit\Framework\Reorderable;
use PHPUnit\Framework\SelfDescribing;
use Toobo\TypeChecker\Type;

class MatchedByTest extends TestCase
{
    /**
     * @test
     */
    public function testSameUnionType(): void
    {
        $left = Type::byString('string|int');
        $right = Type::byString('int|string');

        static::assertTrue($left->matchedBy($right));
        static::assertTrue($left->matchedBy($right)); // this should be cached
        static::assertNotSame($left, $right);
    }

    /**
     * @test
     */
    public function testUnionType(): void
    {
        $left = Type::byString('string|int');
        $right = Type::byString('int');

        static::assertTrue($left->matchedBy($right));
        static::assertTrue($left->matchedBy((string) $right));
        static::assertFalse($right->matchedBy($left));
        static::assertFalse($right->matchedBy((string) $left));
    }

    /**
     * @test
     */
    public function testNullableType(): void
    {
        $left = Type::byString('string|int');
        $right = Type::byString('string|null');

        static::assertFalse($left->matchedBy($right));
        static::assertFalse($right->matchedBy($left));
    }

    /**
     * @test
     */
    public function testIntersectionType(): void
    {
        $func = static fn (Reorderable & SelfDescribing $param): int => 0;

        $left = Type::byReflectionType($this->extractTypeFromCallback($func));
        $right = Type::byString(__CLASS__);

        static::assertTrue($left->matchedBy($right));
        static::assertFalse($right->matchedBy($left));
        static::assertNotSame($left, $right);
    }

    /**
     * @test
     */
    public function testIntersectionTargetType(): void
    {
        $left = Type::byString(TestCase::class);
        $right = Type::byString(__CLASS__ . '&' . \ArrayAccess::class);

        static::assertTrue($left->matchedBy($right));
        static::assertFalse($right->matchedBy($left));
    }

    /**
     * @test
     */
    public function testDnfType(): void
    {
        $this->requireDnfTypes();

        eval( // phpcs:ignore
            <<<'PHP'
             $func = static fn (
                (ArrayAccess & Countable)
                | (PHPUnit\Framework\Reorderable & PHPUnit\Framework\SelfDescribing)
                | null $param
            ): int => 0;
            PHP
        );

        // phpcs:disable VariableAnalysis
        /** @var \Closure $func */
        $left = Type::byReflectionType($this->extractTypeFromCallback($func));
        // phpcs:enable VariableAnalysis

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

        static::assertTrue($left->matchedBy($right1));
        static::assertTrue($left->matchedBy($right2));
        static::assertTrue($left->matchedBy($right3));
        static::assertTrue($left->matchedBy($right4));
        static::assertFalse($left->matchedBy($right5));
        static::assertFalse($left->matchedBy($right6));
        static::assertFalse($left->matchedBy($right7));
        static::assertFalse($left->matchedBy($right8));
        static::assertFalse($left->matchedBy($right9));
        static::assertTrue($left->matchedBy($right10));
        static::assertTrue($left->matchedBy((string) $right1));
        static::assertTrue($left->matchedBy((string) $right2));
        static::assertTrue($left->matchedBy((string) $right3));
        static::assertTrue($left->matchedBy((string) $right4));
        static::assertFalse($left->matchedBy((string) $right5));
        static::assertFalse($left->matchedBy((string) $right6));
        static::assertFalse($left->matchedBy((string) $right7));
        static::assertFalse($left->matchedBy((string) $right8));
        static::assertFalse($left->matchedBy((string) $right9));
        static::assertTrue($left->matchedBy((string) $right10));

        static::assertFalse($right1->matchedBy($left));
        static::assertFalse($right2->matchedBy($left));
        static::assertFalse($right3->matchedBy($left));
        static::assertFalse($right4->matchedBy($left));
        static::assertFalse($right10->matchedBy($left));
        static::assertFalse($right1->matchedBy((string) $left));
        static::assertFalse($right2->matchedBy((string) $left));
        static::assertFalse($right3->matchedBy((string) $left));
        static::assertFalse($right4->matchedBy((string) $left));
        static::assertFalse($right10->matchedBy((string) $left));
    }

    /**
     * @test
     */
    public function testObjectType(): void
    {
        $left = Type::byString('?object');

        $right1 = Type::byString(__CLASS__);
        $right2 = Type::byString('Countable&ArrayAccess');
        $right3 = Type::byString('Countable|ArrayAccess');
        /** @var Type $right4 */
        $right4 = Type::string();
        /** @var Type $right5 */
        $right5 = Type::null();

        eval( // phpcs:ignore
            <<<'PHP'
            enum MyEnum {
                case Foo;
            }
            PHP
        );
        $right6 = Type::byString('MyEnum');
        $right7 = Type::byString('I_DOT_EXISTS');

        static::assertTrue($left->matchedBy($right1));
        static::assertTrue($left->matchedBy($right2));
        static::assertTrue($left->matchedBy($right3));
        static::assertFalse($left->matchedBy($right4));
        static::assertFalse($left->matchedBy($right4));
        static::assertTrue($left->matchedBy($right5));
        static::assertTrue($left->matchedBy($right6));
        static::assertFalse($left->matchedBy($right7));
        static::assertFalse($right1->matchedBy($left));
        static::assertFalse($right2->matchedBy($left));
        static::assertFalse($right3->matchedBy($left));
        static::assertFalse($right5->matchedBy($left));
    }

    /**
     * @test
     */
    public function testIterableType(): void
    {
        $left = Type::byString('iterable');

        $right1 = Type::byString('object');
        $right2 = Type::byString('Countable&Traversable');
        $right3 = Type::byString('Countable|Traversable');
        $right4 = Type::byString('array|int');
        $right5 = Type::byString('array');
        $right6 = Type::byString('array|Iterator');

        static::assertFalse($left->matchedBy($right1));
        static::assertTrue($left->matchedBy($right2));
        static::assertFalse($left->matchedBy($right3));
        static::assertFalse($left->matchedBy($right4));
        static::assertTrue($left->matchedBy($right5));
        static::assertTrue($left->matchedBy($right6));
    }

    /**
     * @test
     */
    public function testCallableType(): void
    {
        $left = Type::byString('callable');

        eval( // phpcs:ignore
            <<<'PHP'
            class MyCallback
            {
                public function __invoke(): void
                {
                }
            }
            PHP
        );

        $right1 = Type::byString('object');
        $right2 = Type::byString('MyCallback&Iterator');
        $right3 = Type::byString('Closure|Traversable');
        $right4 = Type::byString('Closure');
        $right5 = Type::byString('(MyCallback&Iterator)|null');
        $right6 = Type::byString('(MyCallback&Iterator)|Closure');
        $right7 = Type::byString('(MyCallback&Iterator)|Traversable');

        static::assertFalse($left->matchedBy($right1));
        static::assertTrue($left->matchedBy($right2));
        static::assertFalse($left->matchedBy($right3));
        static::assertTrue($left->matchedBy($right4));
        static::assertFalse($left->matchedBy($right5));
        static::assertTrue($left->matchedBy($right6));
        static::assertFalse($left->matchedBy($right7));
    }

    /**
     * @test
     */
    public function testUnaryBool(): void
    {
        $left1 = Type::byString('bool');
        $left2 = Type::byString('bool|null');

        $right1 = Type::byString('true');
        $right2 = Type::byString('false');

        static::assertTrue($left1->matchedBy($right1));
        static::assertTrue($left1->matchedBy($right2));
        static::assertTrue($left2->matchedBy($right1));
        static::assertTrue($left2->matchedBy($right2));
        static::assertFalse($right1->matchedBy($right2));
        static::assertFalse($right2->matchedBy($right1));
    }
}
