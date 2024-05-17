<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use Toobo\TypeChecker\Type;

class ByReflectionTypeTest extends TestCase
{
    /**
     * @test
     */
    public function testStandaloneType(): void
    {
        $ref = $this->extractTypeFromCallback(static fn (mixed $param): string => '');

        $type = Type::byReflectionType($ref);

        /** @var Type $mixed */
        $mixed = Type::mixed();

        static::assertSame('mixed', (string) $type);
        static::assertTrue($type->equals($mixed));
        static::assertNotSame($type, $mixed);
        static::assertTrue($type->isStandalone());
        static::assertFalse($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());
    }

    /**
     * @test
     * @dataProvider provideLateStaticBindingThrow
     */
    public function testLateStaticBindingThrows(string $type): void
    {
        $before = match (random_int(1, 12)) {
            1, 5, 9 => '',
            2, 6, 10 => '?',
            3, 7, 11 => 'Foo|',
            4, 8, 12 => 'string|',
        };

        $after = '';
        if ($before !== '?') {
            $after = match (random_int(1, 9)) {
                1, 4, 7 => '',
                2, 5, 8 => '|null',
                3, 6, 9 => '|callable',
            };
        }

        // phpcs:disable VariableAnalysis, Squiz.PHP.Eval
        eval(sprintf('$func = fn (): %s%s%s => 1;', $before, $type, $after));
        /**
         * @var \Closure $func
         * @var \ReflectionType $ref
         */
        $ref = (new \ReflectionFunction($func))->getReturnType();
        // phpcs:enable VariableAnalysis, Squiz.PHP.Eval

        $this->expectExceptionMessageMatches('/late/i');

        Type::byReflectionType($ref);
    }

    /**
     * @return \Generator
     */
    public static function provideLateStaticBindingThrow(): \Generator
    {
        yield from [
            ['static'],
            ['self'],
            ['parent'],
        ];
    }

    /**
     * @test
     */
    public function testCacheReturnDifferentType(): void
    {
        $ref = $this->extractTypeFromCallback(static fn (mixed $param): string => '');

        $type1 = Type::byReflectionType($ref);
        $type2 = Type::byReflectionType($ref);

        static::assertTrue($type1->equals($type2));
        static::assertNotSame($type1, $type2);
    }

    /**
     * @test
     */
    public function testUnionType(): void
    {
        $ref = $this->extractTypeFromCallback(static fn (null|string|int $param): int => 0);

        $type = Type::byReflectionType($ref);

        $byString = Type::byString('string|int|null');

        static::assertSame('int|null|string', (string) $type);
        static::assertTrue($type->equals($byString));
        static::assertNotSame($type, $byString);
        static::assertFalse($type->isStandalone());
        static::assertTrue($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());
    }

    /**
     * @test
     */
    public function testNullableType(): void
    {
        $ref = $this->extractTypeFromCallback(static fn (?string $param): int => 0);

        $type = Type::byReflectionType($ref);

        $byString = Type::byString('null|string');
        $byString2 = Type::byString('?string');

        static::assertSame('null|string', (string) $type);
        static::assertTrue($type->equals($byString));
        static::assertTrue($type->equals($byString2));
        static::assertNotSame($type, $byString);
        static::assertNotSame($byString, $byString2);
        static::assertFalse($type->isStandalone());
        static::assertTrue($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());
    }

    /**
     * @test
     */
    public function testIntersectionType(): void
    {
        $ref = $this->extractTypeFromCallback(static fn (\Countable&\ArrayAccess $param): int => 0);

        $type = Type::byReflectionType($ref);

        $byString = Type::byString('ArrayAccess&Countable');

        static::assertSame('ArrayAccess&Countable', (string) $type);
        static::assertTrue($type->equals($byString));
        static::assertNotSame($type, $byString);
        static::assertFalse($type->isStandalone());
        static::assertFalse($type->isUnion());
        static::assertTrue($type->isIntersection());
        static::assertFalse($type->isDnf());
    }

    /**
     * @test
     */
    public function testResourceClass(): void
    {
        $ref = $this->extractTypeFromCallback(
            /** @psalm-suppress ReservedWord */
            static fn (\resource $param): int => 0
        );

        $type = Type::byReflectionType($ref);

        $byString = Type::byString('\\resource');
        /** @var Type $resource */
        $resource = Type::resource();

        static::assertSame('\\resource', (string) $type);
        static::assertSame('resource', (string) $resource);
        static::assertFalse($type->equals($resource));
        static::assertTrue($type->equals($byString));
        static::assertNotSame($type, $byString);
        static::assertNotSame($type, $resource);
        static::assertFalse($resource->isArgumentSafe());
        static::assertFalse($resource->isReturnSafe());
        static::assertFalse($resource->isPropertySafe());
        static::assertTrue($type->isArgumentSafe());
        static::assertTrue($type->isReturnSafe());
        static::assertTrue($type->isPropertySafe());
    }

    /**
     * @test
     */
    public function testComplexType(): void
    {
        $this->requireDnfTypes();

        eval( // phpcs:ignore
            <<<'PHP'
            $func = static function (
                (\Countable & \ArrayAccess)
                | (\Countable & \Stringable)
                | null
                | float
                | false $param
            ): void {};
            PHP
        );

        // phpcs:disable VariableAnalysis
        /** @var \Closure $func */
        $ref = $this->extractTypeFromCallback($func);
        // phpcs:enable VariableAnalysis
        $type = Type::byReflectionType($ref);

        $string1 = (string) $ref;
        $strParts = explode('|', $string1);
        shuffle($strParts);
        $string2 = implode(' | ', $strParts);

        $byString1 = Type::byString($string1);
        $byString2 = Type::byString($string2);

        static::assertTrue($type->isNullable());
        static::assertFalse($type->isA('object'));

        static::assertSame((string) $type, (string) $byString1);
        static::assertSame((string) $byString1, (string) $byString2);
        static::assertSame(sprintf('"%s"', $type), json_encode($type));
        static::assertTrue($type->isDnf());
        static::assertTrue($byString1->isDnf());
        static::assertTrue($byString2->isDnf());
        static::assertTrue($type->equals($byString1));
        static::assertTrue($byString1->equals($byString2));
        static::assertNotSame($type, $byString1);
        static::assertNotSame($byString1, $byString2);
    }

    /**
     * @test
     */
    public function testReflectionClass(): void
    {
        $type = Type::byReflectionType(new \ReflectionClass($this));

        static::assertTrue($type->satisfiedBy($this));
        static::assertTrue(Type::byString('object')->matchedBy($type));
        static::assertTrue($type->isA(TestCase::class));
        static::assertTrue($type->isA(TestCase::class . '|Foo'));
        static::assertFalse($type->isA(TestCase::class . '&Foo'));
        static::assertSame(__CLASS__, (string) $type);

        static::assertTrue($type->isStandalone());
        static::assertFalse($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());

        static::assertTrue($type->isPropertySafe());
        static::assertTrue($type->isArgumentSafe());
        static::assertTrue($type->isReturnSafe());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testReflectionClassResource(): void
    {
        eval('class resource {}'); // phpcs:disable

        /** @psalm-suppress ReservedWord */
        $type = Type::byReflectionType(new \ReflectionClass(\resource::class));

        static::assertTrue($type->satisfiedBy(new \resource()));
        static::assertTrue($type->isA('object'));
        static::assertFalse($type->isA('resource'));
        static::assertTrue($type->isA('\\resource'));
        static::assertSame('\\resource', (string) $type);
    }
}
