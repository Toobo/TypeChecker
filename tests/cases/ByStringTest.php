<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use Toobo\TypeChecker\Type;

class ByStringTest extends TestCase
{
    /**
     * @test
     */
    public function testSimpleTypes(): void
    {
        $type = Type::byString('string|true|null');

        static::assertSame('null|string|true', (string) $type);

        static::assertTrue($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());
        static::assertFalse($type->isStandalone());
    }

    /**
     * @test
     */
    public function testMixedAloneAllowed(): void
    {
        $type = Type::byString('mixed');

        static::assertSame('mixed', (string) $type);

        static::assertFalse($type->isUnion());
        static::assertFalse($type->isIntersection());
        static::assertFalse($type->isDnf());
        static::assertTrue($type->isStandalone());
    }

    /**
     * @test
     */
    public function testDuplicateIntersectionThrows(): void
    {
        $this->expectExceptionMessageMatches('/duplicate/i');

        Type::byString('(Foo&Bar)|int|(Bar&Foo)|null|int');
    }

    /**
     * @test
     */
    public function testDuplicateInIntersectionThrows(): void
    {
        $this->expectExceptionMessageMatches('/duplicate/i');

        Type::byString('Foo&Bar&\Foo');
    }

    /**
     * @test
     */
    public function testEmptyTypeThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('Foo&&Bar');
    }

    /**
     * @test
     */
    public function testMixedNullableThrows(): void
    {
        $this->expectExceptionMessageMatches('/mixed/');

        Type::byString('?mixed');
    }

    /**
     * @test
     */
    public function testReservedWordThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/i');

        Type::byString('Meh|(Yield&Foo)');
    }

    /**
     * @test
     */
    public function testNullableReservedWordThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/i');

        Type::byString('?goto');
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
                3, 6, 9 => '|(ArrayAccess&Countable)',
            };
        }

        $this->expectExceptionMessageMatches('/late/i');

        Type::byString($before . $type . $after);
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
     * @dataProvider provideStandaloneInUnionThrows
     */
    public function testStandaloneInUnionThrows(string $type1, string $type2): void
    {
        $types = [$type1, $type2];
        sort($types);

        $this->expectExceptionMessageMatches('/standalone/');
        Type::byString(implode('|', $types));
    }

    /**
     * @return \Generator
     */
    public static function provideStandaloneInUnionThrows(): \Generator
    {
        foreach (['mixed', 'void', 'never', 'resource'] as $standalone) {
            yield "int|{$standalone}" => [$standalone, 'int'];
            yield "A|{$standalone}" => [$standalone, 'A'];
            yield "Zed|{$standalone}" => [$standalone, 'Zed'];
        }
    }

    /**
     * @test
     * @dataProvider provideScalarInIntersectionThrows
     */
    public function testScalarInIntersectionThrows(string $type1, string $type2): void
    {
        $types = [$type1, $type2];
        sort($types);

        $this->expectExceptionMessageMatches('/intersection/');
        Type::byString(implode('&', $types));
    }

    /**
     * @return \Generator
     */
    public static function provideScalarInIntersectionThrows(): \Generator
    {
        $types = [
            'string',
            'int',
            'float',
            'bool',
            'array',
            'object',
            'callable',
            'iterable',
            'true',
            'false',
            'null',
            'mixed',
            'void',
            'never',
            'resource',
        ];

        foreach ($types as $type) {
            yield "A|{$type}" => [$type, 'A'];
            yield "Zed|{$type}" => [$type, 'Zed'];
        }
    }

    /**
     * @test
     * @dataProvider provideTrueFalseUnionThrows
     */
    public function testTrueFalseUnionThrows(array $types): void
    {
        $this->expectExceptionMessageMatches('/bool/');

        /** @var list<string> $types */
        Type::byString(implode('|', $types));
    }

    /**
     * @return \Generator
     */
    public static function provideTrueFalseUnionThrows(): \Generator
    {
        yield from [
            [['true', 'false']],
            [['false', 'true']],
            [['A', 'false', 'true']],
            [['A', 'true', 'false']],
            [['false', 'A', 'true']],
            [['true', 'A', 'false']],
            [['true', 'false', 'A']],
            [['false', 'true', 'A']],
        ];
    }

    /**
     * @test
     */
    public function testNullableNullThrows(): void
    {
        $this->expectExceptionMessageMatches('/null/');

        Type::byString('?null');
    }

    /**
     * @test
     * @dataProvider provideCompositeNullableThrows
     */
    public function testCompositeNullableThrows(string $type): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString($type);
    }

    /**
     * @return \Generator
     */
    public static function provideCompositeNullableThrows(): \Generator
    {
        return yield from [
            ['A|?B'],
            ['?A|B'],
            ['A|?B|C'],
            ['A&?B'],
            ['?A&B'],
            ['A&?B&C'],
        ];
    }

    /**
     * @test
     */
    public function testSingleNullableScalarWorks(): void
    {
        $type = Type::byString('?string');

        static::assertSame('null|string', (string) $type);
    }

    /**
     * @test
     */
    public function testSingleNullableObjectWorks(): void
    {
        $type = Type::byString('?Foo');

        static::assertSame('Foo|null', (string) $type);
    }

    /**
     * @test
     */
    public function testBuiltInTypeWithLeadingSlashFails(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('\\string');
    }

    /**
     * @test
     */
    public function testNullableBuiltInTypeWithLeadingSlashFails(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('?\\string');
    }

    /**
     * @test
     */
    public function testEmptyStringThrows(): void
    {
        $this->expectException(\TypeError::class);

        Type::byString('');
    }

    /**
     * @test
     */
    public function testEndWithSlashThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid type.+?Foo/');

        Type::byString('Foo\\');
    }

    /**
     * @test
     */
    public function testEndWithAndThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('Foo&Bar&');
    }

    /**
     * @test
     */
    public function testStartWithAndThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('&Foo&Bar');
    }

    /**
     * @test
     */
    public function testEndWithOrThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('Foo|Bar|');
    }

    /**
     * @test
     */
    public function testStartWithOrThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid/');

        Type::byString('|Foo|Bar');
    }

    /**
     * @test
     */
    public function testInvalidSyntaxThrows(): void
    {
        $this->expectExceptionMessageMatches('/valid type.+?Fo-o/');

        Type::byString('Fo-o');
    }

    /**
     * @test
     */
    public function testInvalidTypeAsMagicMethodThrows(): void
    {
        $this->expectExceptionMessageMatches('/undefined/');

        Type::meh();
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testNormalize(): void
    {
        $one = Type::byString('String|inT|OBJECT|Foo');
        $two = Type::byString('string|int|object|\Foo');
        $three = Type::byString('stRIng|Int|Object|Foo');
        $four = Type::byString(
            '(Countable&ArrayAccess)'
            . '|(PHPUnit\Framework\Assert&Countable)'
            . '|NULL'
            . '|(Countable&Stringable)'
            . '|float'
        );
        $five = Type::byString('Foo&Bar');
        $six = Type::byString(
            '(Countable & ArrayAccess) '
            . '| (PHPUnit\Framework\Assert & Countable) '
            . '| NULL '
            . '| (Countable & Stringable) '
            . '| float'
        );
        $seven = Type::byString('?STRING');


        static::assertSame((string) $one, (string) $two);
        static::assertSame((string) $two, (string) $three);
        static::assertSame(
            '(ArrayAccess&Countable)'
            . '|(Countable&PHPUnit\Framework\Assert)'
            . '|(Countable&Stringable)'
            . '|float'
            . '|null',
            (string) $four,
        );
        static::assertSame('Bar&Foo', (string) $five);
        static::assertSame((string) $four, (string) $six);
        static::assertSame('null|string', (string) $seven);
    }

    /**
     * @test
     */
    public function testTypeIssers(): void
    {
        $one = Type::byString('String|inT|OBJECT|Foo');
        $two = Type::byString('Foo&Bar');
        $three = Type::byString('(Foo&Bar)|Baz');
        $four = Type::byString('mixed');
        $five = Type::byString('?int');
        $six = Type::byString('null|(Foo&Bar)|string|float');

        static::assertTrue($one->isUnion());
        static::assertFalse($one->isIntersection());
        static::assertFalse($one->isDnf());
        static::assertFalse($one->isStandalone());

        static::assertFalse($two->isUnion());
        static::assertTrue($two->isIntersection());
        static::assertFalse($two->isDnf());
        static::assertFalse($two->isStandalone());

        static::assertFalse($three->isUnion());
        static::assertFalse($three->isIntersection());
        static::assertTrue($three->isDnf());
        static::assertFalse($three->isStandalone());

        static::assertFalse($four->isUnion());
        static::assertFalse($four->isIntersection());
        static::assertFalse($four->isDnf());
        static::assertTrue($four->isStandalone());

        static::assertTrue($five->isUnion());
        static::assertFalse($five->isIntersection());
        static::assertFalse($five->isDnf());
        static::assertFalse($five->isStandalone());

        static::assertFalse($six->isUnion());
        static::assertFalse($six->isIntersection());
        static::assertTrue($six->isDnf());
        static::assertFalse($six->isStandalone());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testResourceAsClass(): void
    {
        /** @var Type $builtIn */
        $builtIn = Type::resource();
        $asClass = Type::byString('\\resource');

        static::assertSame('resource', (string) $builtIn);
        static::assertSame('\\resource', (string) $asClass);
        static::assertFalse($builtIn->isArgumentSafe());
        static::assertFalse($builtIn->isReturnSafe());
        static::assertFalse($builtIn->isPropertySafe());
        static::assertTrue($asClass->isArgumentSafe());
        static::assertTrue($asClass->isReturnSafe());
        static::assertTrue($asClass->isPropertySafe());
    }

    /**
     * @test
     */
    public function testNullableEquals(): void
    {
        static::assertTrue(Type::byString('?string')->equals(Type::byString('string|null')));
    }

    /**
     * @test
     */
    public function testNotPropertySafe(): void
    {
        foreach (['resource', 'never', 'void', 'callable', '?callable'] as $type) {
            static::assertFalse(Type::byString($type)->isPropertySafe());
        }
    }

    /**
     * @test
     */
    public function testNotArgumentSafe(): void
    {
        foreach (['resource', 'never', 'void'] as $type) {
            static::assertFalse(Type::byString($type)->isArgumentSafe());
        }
    }

    /**
     * @test
     */
    public function testNotReturnSafe(): void
    {
        static::assertFalse(Type::byString('resource')->isReturnSafe());
    }

    /**
     * @test
     */
    public function testStaticConstructorsDifferentInstances(): void
    {
        static::assertNotSame(Type::int(), Type::int());
    }
}
