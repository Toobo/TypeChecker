<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use Toobo\TypeChecker\Type;

class SatisfiedByTest extends TestCase
{
    /**
     * @test
     */
    public function testSimpleMatch(): void
    {
        $type = Type::byString('string');

        static::assertTrue($type->satisfiedBy(''));
        static::assertTrue($type->satisfiedBy('foo'));
        static::assertTrue($type->satisfiedBy('1'));
        static::assertFalse($type->satisfiedBy(1));
    }

    /**
     * @test
     */
    public function testUnaryFalse(): void
    {
        $type = Type::byString('false');

        static::assertFalse($type->satisfiedBy(true));
        static::assertFalse($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(0));
        static::assertFalse($type->satisfiedBy(null));
        static::assertTrue($type->satisfiedBy(false));
    }

    /**
     * @test
     */
    public function testUnaryTrue(): void
    {
        /** @var Type $type */
        $type = Type::true();

        static::assertTrue($type->satisfiedBy(true));
        static::assertFalse($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(0));
        static::assertFalse($type->satisfiedBy(null));
        static::assertFalse($type->satisfiedBy(false));
    }

    /**
     * @test
     */
    public function testUnaryNull(): void
    {
        $type = Type::byString('null');

        static::assertFalse($type->satisfiedBy(true));
        static::assertFalse($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(0));
        static::assertFalse($type->satisfiedBy(''));
        static::assertTrue($type->satisfiedBy(null));
        static::assertFalse($type->satisfiedBy(false));
    }

    /**
     * @test
     */
    public function testMixedMatchAll(): void
    {
        /** @var Type $type */
        $type = Type::mixed();

        static::assertTrue($type->satisfiedBy(''));
        static::assertTrue($type->satisfiedBy(false));
        static::assertTrue($type->satisfiedBy(null));
        static::assertTrue($type->satisfiedBy(true));
        static::assertTrue($type->satisfiedBy($this));
        static::assertTrue($type->satisfiedBy(static fn (): int => 0));
        static::assertTrue($type->satisfiedBy([]));
    }

    /**
     * @test
     */
    public function testVoidMatchNothing(): void
    {
        /** @var Type $type */
        $type = Type::void();

        static::assertFalse($type->satisfiedBy(''));
        static::assertFalse($type->satisfiedBy('void'));
        static::assertFalse($type->satisfiedBy(false));
        static::assertFalse($type->satisfiedBy(null));
        static::assertFalse($type->satisfiedBy(true));
        static::assertFalse($type->satisfiedBy($this));
        static::assertFalse($type->satisfiedBy(static fn (): int => 0));
        static::assertFalse($type->satisfiedBy([]));
    }

    /**
     * @test
     */
    public function testNeverMatchNothing(): void
    {
        /** @var Type $type */
        $type = Type::never();

        static::assertFalse($type->satisfiedBy(''));
        static::assertFalse($type->satisfiedBy('never'));
        static::assertFalse($type->satisfiedBy(false));
        static::assertFalse($type->satisfiedBy(null));
        static::assertFalse($type->satisfiedBy(true));
        static::assertFalse($type->satisfiedBy($this));
        static::assertFalse($type->satisfiedBy(static fn (): int => 0));
        static::assertFalse($type->satisfiedBy([]));
    }

    /**
     * @test
     */
    public function testScalarUnion(): void
    {
        $type = Type::byString('string|int');

        static::assertTrue($type->satisfiedBy(''));
        static::assertTrue($type->satisfiedBy('foo'));
        static::assertTrue($type->satisfiedBy('1'));
        static::assertTrue($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(1.0));
        static::assertFalse($type->satisfiedBy(null));
    }

    /**
     * @test
     */
    public function testNullableScalarUnion(): void
    {
        $type = Type::byString('int|null');

        static::assertFalse($type->satisfiedBy('1'));
        static::assertTrue($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(1.0));
        static::assertTrue($type->satisfiedBy(null));
    }

    /**
     * @test
     */
    public function testIntersection(): void
    {
        $countable = new class () implements \Countable {
            public function count(): int
            {
                return 1;
            }
        };

        $object = new \ArrayObject();

        $type = Type::byString(\Countable::class . '&' . \ArrayAccess::class);

        static::assertFalse($type->satisfiedBy('1'));
        static::assertTrue($type->satisfiedBy($object));
        static::assertFalse($type->satisfiedBy($countable));
        static::assertFalse($type->satisfiedBy(1));
        static::assertFalse($type->satisfiedBy(null));
    }

    /**
     * @test
     */
    public function testNullableIntersection(): void
    {
        $countable = new class () implements \Countable {
            public function count(): int
            {
                return 1;
            }
        };

        $object = new \ArrayObject();

        $type = Type::byString('(Countable&ArrayAccess)|null');

        static::assertTrue($type->satisfiedBy($object));
        static::assertFalse($type->satisfiedBy($countable));
        static::assertTrue($type->satisfiedBy(null));
    }

    /**
     * @test
     */
    public function testDnfWithScalar(): void
    {
        $countable = new class () implements \Countable {
            public function count(): int
            {
                return 1;
            }
        };

        $object = new \ArrayObject();

        $type = Type::byString('(Countable&ArrayAccess)|string');

        static::assertTrue($type->satisfiedBy($object));
        static::assertFalse($type->satisfiedBy($countable));
        static::assertFalse($type->satisfiedBy(null));
        static::assertTrue($type->satisfiedBy(''));
    }

    /**
     * @test
     */
    public function testDnfWithObjectsAndScalarAndNull(): void
    {
        $countable = new class () implements \Countable {
            public function count(): int
            {
                return 1;
            }
        };

        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return '';
            }
        };

        $countableStringable = new class () implements \Countable, \Stringable {
            public function __toString(): string
            {
                return '';
            }

            public function count(): int
            {
                return 1;
            }
        };

        $type = Type::byString(
            '(Countable&ArrayAccess)'
            . '|(PHPUnit\Framework\Assert&Countable)'
            . '|null'
            . '|(Countable&Stringable)'
            . '|float'
            . '|false'
        );

        static::assertTrue($type->satisfiedBy(new \ArrayObject()));
        static::assertFalse($type->satisfiedBy($countable));
        static::assertFalse($type->satisfiedBy($stringable));
        static::assertTrue($type->satisfiedBy($countableStringable));
        static::assertTrue($type->satisfiedBy(null));
        static::assertFalse($type->satisfiedBy(''));
        static::assertTrue($type->satisfiedBy(0.0));
        static::assertTrue($type->satisfiedBy($this));
        static::assertFalse($type->satisfiedBy(true));
        static::assertTrue($type->satisfiedBy(false));
    }

    /**
     * @test
     */
    public function testResource(): void
    {
        /** @var Type $type */
        $type = Type::resource();
        $type2 = Type::byString('resource');

        static::assertTrue($type->satisfiedBy(STDERR));
        static::assertTrue($type->satisfiedBy(STDOUT));
        static::assertTrue($type2->satisfiedBy(STDOUT));
        static::assertTrue($type2->satisfiedBy(STDERR));
        static::assertFalse($type->satisfiedBy(''));
        static::assertFalse($type->satisfiedBy('resource'));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testMatchedByUsesCache(): void
    {
        $type = Type::byString('string');
        static::assertTrue($type->satisfiedBy(''));
        static::assertTrue($type->matchedBy(Type::string()));
    }
}
