<?php

declare(strict_types=1);

namespace Toobo\TypeChecker;

/**
 * @method static Type string()
 * @method static Type int()
 * @method static Type float()
 * @method static Type bool()
 * @method static Type array()
 * @method static Type object()
 * @method static Type mixed()
 * @method static Type callable()
 * @method static Type iterable()
 * @method static Type true()
 * @method static Type false()
 * @method static Type null()
 * @method static Type never()
 * @method static Type void()
 * @method static Type resource()
 */
final class Type implements \Stringable, \JsonSerializable
{
    private const TYPE_REGEX = '[a-z_\x80-\xff][a-z0-9_\x80-\xff]*';

    private const DEFAULT_TYPES = [
        'mixed' => 'mixed',
        'string' => 'string',
        'int' => 'int',
        'float' => 'float',
        'bool' => 'bool',
        'array' => 'array',
        'resource' => 'resource',
        'object' => 'object',
        'callable' => 'callable',
        'iterable' => 'iterable',
        'true' => 'true',
        'false' => 'false',
        'null' => 'null',
        'never' => 'never',
        'void' => 'void',
    ];

    private const ISSERS = [
        ['string'],
        ['int'],
        ['float'],
        ['bool'],
        ['array'],
        ['resource'],
        ['object'],
        ['callable'],
        ['iterable'],
    ];

    private const UNARY_TYPES_MAP = [
        'true' => true,
        'false' => false,
        'null' => null,
    ];

    private const SIMPLE_REF_TYPES = [
        ['string'],
        ['int'],
        ['float'],
        ['bool'],
        ['array'],
        ['mixed'],
        ['true'],
        ['false'],
        ['null'],
        ['never'],
        ['void'],
        ['resource'],
    ];

    private const SIMPLE_TARGET_TYPES = [
        ['string'],
        ['int'],
        ['float'],
        ['bool'],
        ['array'],
        ['mixed'],
        ['true'],
        ['false'],
        ['null'],
        ['never'],
        ['void'],
        ['resource'],
        ['object'],
        ['callable'],
        ['iterable'],
    ];

    private const RESERVED_WORDS = [
        '__halt_compiler' => 0,
        'abstract' => 0,
        'and' => 0,
        'as' => 0,
        'break' => 0,
        'case' => 0,
        'catch' => 0,
        'class' => 0,
        'clone' => 0,
        'const' => 0,
        'continue' => 0,
        'declare' => 0,
        'default' => 0,
        'die' => 0,
        'do' => 0,
        'echo' => 0,
        'else' => 0,
        'elseif' => 0,
        'empty' => 0,
        'enddeclare' => 0,
        'endfor' => 0,
        'endforeach' => 0,
        'endif' => 0,
        'endswitch' => 0,
        'endwhile' => 0,
        'eval' => 0,
        'exit' => 0,
        'extends' => 0,
        'final' => 0,
        'finally' => 0,
        'fn' => 0,
        'for' => 0,
        'foreach' => 0,
        'function' => 0,
        'global' => 0,
        'goto' => 0,
        'if' => 0,
        'implements' => 0,
        'include' => 0,
        'include_once' => 0,
        'instanceof' => 0,
        'insteadof' => 0,
        'interface' => 0,
        'isset' => 0,
        'list' => 0,
        'match' => 0,
        'namespace' => 0,
        'new' => 0,
        'or' => 0,
        'print' => 0,
        'private' => 0,
        'protected' => 0,
        'public' => 0,
        'readonly' => 0,
        'require' => 0,
        'require_once' => 0,
        'return' => 0,
        'switch' => 0,
        'throw' => 0,
        'trait' => 0,
        'try' => 0,
        'unset' => 0,
        'use' => 0,
        'var' => 0,
        'while' => 0,
        'xor' => 0,
        'yield' => 0,
    ];

    /** @var array<string, Type> */
    private static array $factoryCache = [];

    /** @var array<string, bool> */
    private static array $matchCache = [];

    /** @var list<string> */
    private array $ids = [];

    /** @var array<string, bool> */
    private array $intersections = [];

    /** @var non-empty-string|null */
    private ?string $calculatedId = null;

    private bool $hasTrue = false;
    private bool $hasFalse = false;
    private bool $isUnion = false;
    private bool $isIntersection = false;
    private bool $isDnf = false;

    /**
     * @param \ReflectionType|\ReflectionClass $paramType
     * @return Type
     */
    public static function byReflectionType(\ReflectionType|\ReflectionClass $paramType): Type
    {
        if ($paramType instanceof \ReflectionClass) {
            return Type::byString('\\' . $paramType->getName());
        }

        $key = (string) $paramType;
        if ($key === 'resource') {
            $key = '\\resource';
        }

        if (isset(static::$factoryCache[$key])) {
            return clone static::$factoryCache[$key];
        }

        if ($paramType instanceof \ReflectionNamedType) {
            $type = static::createFromNamedType($paramType);
            static::$factoryCache[$key] = $type;
            static::$factoryCache[$type->id()] = $type;

            return $type;
        }

        if ($paramType instanceof \ReflectionIntersectionType) {
            $type = static::createFromIntersectionType($paramType);
            static::$factoryCache[$key] = $type;
            static::$factoryCache[$type->id()] = $type;

            return $type;
        }

        /** @var \ReflectionUnionType $paramType */

        $type = static::createFromUnionType($paramType);
        static::$factoryCache[$key] = $type;
        static::$factoryCache[$type->id()] = $type;

        return $type;
    }

    /**
     * @param string $typeDef
     * @return Type
     */
    public static function byString(string $typeDef): Type
    {
        if (isset(static::$factoryCache[$typeDef])) {
            return clone static::$factoryCache[$typeDef];
        }

        if ($typeDef === '') {
            throw new \TypeError(
                sprintf(
                    '%s: Argument #1 ($typeDef) must be a non-empty string.',
                    __METHOD__,
                )
            );
        }

        if (($typeDef[0] === '|') || ($typeDef[-1] === '|')) {
            static::bailForInvalidDef($typeDef);
        }

        /** @var Type|null $type */
        $type = null;
        $typePart = strtok($typeDef, '|');
        $nullableFound = false;
        $unions = 0;
        while ($typePart !== false) {
            $unions++;
            $names = [];
            $intersect = explode('&', rtrim(ltrim(trim($typePart), '('), ')'));
            $typePart = strtok('|');
            foreach ($intersect as $iType) {
                $iType = trim($iType);
                (($iType === '') || ($iType === '\\')) and static::bailForInvalidDef($typeDef);
                [$name, $nullable, $prefix] = static::normalizeTypeNameString($iType);
                static::assertValidTypeString($name, $typeDef);
                $names[] = $prefix . $name;
                $nullableFound = $nullableFound || $nullable;
            }
            if ($nullableFound && isset($names[1])) {
                // "?" syntax not allowed in intersection
                static::bailForInvalidDef($typeDef);
            }
            $type ??= new static();
            $type = $type->addUnionTypeChecked(...$names);
        }

        /** @var Type $type $type can be null only if def being empty, but then we already bailed */

        if ($nullableFound) {
            // "?" syntax not allowed in union
            ($unions > 1) and static::bailForInvalidDef($typeDef);
            $type = $type->addUnionTypeChecked('null');
        }

        static::$factoryCache[$typeDef] = $type;
        static::$factoryCache[$type->id()] = $type;

        return $type;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return Type
     */
    public static function __callStatic(string $name, array $arguments): Type
    {
        if (!isset(self::DEFAULT_TYPES[$name])) {
            throw new \Error(sprintf('Call to undefined method %s::%s', __CLASS__, $name));
        }

        if (isset(static::$factoryCache[$name])) {
            return clone static::$factoryCache[$name];
        }

        /** @var non-empty-string $name */
        $instance = new static([[$name]]);
        $instance->ids = [$name];
        static::$factoryCache[$name] = $instance;

        return $instance;
    }

    /**
     * @param array $data
     * @return never
     *
     * @codeCoverageIgnore
     */
    public static function __set_state(array $data): never
    {
        throw new \Error(sprintf('Unserialization of %s is not allowed.', __CLASS__));
    }

    /**
     * @param \ReflectionNamedType $type
     * @return Type
     */
    private static function createFromNamedType(\ReflectionNamedType $type): Type
    {
        $instance = new static();

        [$typeName, $hasNull] = static::splitNull($type);
        $instance = $instance->addUnionTypeUnchecked($typeName);
        $hasNull and $instance = $instance->addUnionTypeUnchecked('null');

        return $instance;
    }

    /**
     * @param \ReflectionIntersectionType $type
     * @return Type
     */
    private static function createFromIntersectionType(\ReflectionIntersectionType $type): Type
    {
        $types = [];

        foreach ($type->getTypes() as $namedType) {
            /** @var \ReflectionNamedType $namedType */
            $types[] = $namedType->getName();
        }

        return (new static())->addUnionTypeUnchecked(...$types);
    }

    /**
     * @param \ReflectionUnionType $type
     * @return Type
     */
    private static function createFromUnionType(\ReflectionUnionType $type): Type
    {
        $instance = new static();

        foreach ($type->getTypes() as $aType) {
            if ($aType instanceof \ReflectionNamedType) {
                $instance = $instance->merge(static::createFromNamedType($aType));
                continue;
            }

            /** @var \ReflectionIntersectionType $aType */
            $instance = $instance->merge(static::createFromIntersectionType($aType));
        }

        return $instance;
    }

    /**
     * @param \ReflectionNamedType $type
     * @return list{non-empty-string, bool}
     */
    private static function splitNull(\ReflectionNamedType $type): array
    {
        $name = $type->getName();
        static::assertNotLateStaticBinding($name);
        ($name === 'resource') and $name = '\\resource';
        $hasNull = $type->allowsNull() && ($name !== 'null') && ($name !== 'mixed');

        return [$name, $hasNull];
    }

    /**
     * @param non-empty-string $type
     * @return list{non-empty-string, bool, string}
     */
    private static function normalizeTypeNameString(string $type): array
    {
        $hasNull = $type[0] === '?';
        $name = $hasNull ? substr($type, 1) : $type;
        $nameLower = strtolower($name);

        if ($nameLower === "\\resource") {
            /** @var non-empty-string $nameTrimmed */
            $nameTrimmed = ltrim($name, '\\');

            return [$nameTrimmed, $hasNull, '\\'];
        }

        $nameLowerTrimmed = ltrim($nameLower, '\\');
        if (isset(self::DEFAULT_TYPES[$nameLowerTrimmed])) {
            if ($nameLowerTrimmed !== $nameLower) {
                // Returning '\\' to make the next check fail, because we don't allow things like
                // "\string", "\int" and so on.
                /** @infection-ignore-all */
                return ['\\', false, ''];
            }
            /** @var non-empty-string $nameLower */
            return [$nameLower, $hasNull, ''];
        }
        /** @var non-empty-string $nameTrimmed */
        $nameTrimmed = ltrim($name, '\\');

        return [$nameTrimmed, $hasNull, ''];
    }

    /**
     * @param non-empty-string $type
     * @param non-empty-string $typeDef
     * @return void
     */
    private static function assertValidTypeString(string $type, string $typeDef): void
    {
        $test = strtolower($type);
        if (
            isset(self::RESERVED_WORDS[$test])
            || !preg_match('~^' . self::TYPE_REGEX . '(?:\\\\' . self::TYPE_REGEX . ')*$~', $test)
        ) {
            static::bailForInvalidDef($typeDef);
        }

        static::assertNotLateStaticBinding($test);
    }

    /**
     * @param string $typeDef
     * @return never
     */
    private static function bailForInvalidDef(string $typeDef): never
    {
        throw new \Error(
            sprintf(
                '%s::%s: Argument #1 ($typeDef) must be a valid type definition, %s given.',
                __CLASS__,
                'newByString',
                $typeDef
            )
        );
    }

    /**
     * @param string $type
     * @return void
     */
    private static function assertNotLateStaticBinding(string $type): void
    {
        $invalid = match ($type) {
            'self' => 'self',
            'static' => 'static',
            'parent' => 'parent',
            default => null
        };

        if ($invalid === null) {
            return;
        }

        throw new \Error(
            sprintf(
                '%s does not support "late state binding" type "%s".',
                __CLASS__,
                $invalid
            )
        );
    }

    /**
     * @param list<list<non-empty-string>> $types
     */
    private function __construct(private array $types = [])
    {
    }

    /**
     */
    private function __clone()
    {
    }

    /**
     * @return bool
     */
    public function isPropertySafe(): bool
    {
        return $this->types !== [['resource']]
            && $this->types !== [['never']]
            && $this->types !== [['void']]
            && $this->types !== [['callable']]
            && $this->types !== [['callable'], ['null']];
    }

    /**
     * @return bool
     */
    public function isArgumentSafe(): bool
    {
        return $this->types !== [['resource']]
            && $this->types !== [['never']]
            && $this->types !== [['void']];
    }

    /**
     * @return bool
     */
    public function isReturnSafe(): bool
    {
        return $this->types !== [['resource']];
    }

    /**
     * @return bool
     */
    public function isIntersection(): bool
    {
        return $this->isIntersection;
    }

    /**
     * @return bool
     */
    public function isUnion(): bool
    {
        return $this->isUnion;
    }

    /**
     * @return bool
     */
    public function isDnf(): bool
    {
        return $this->isDnf;
    }

    /**
     * @return bool
     */
    public function isStandalone(): bool
    {
        return !$this->isIntersection && !$this->isUnion && !$this->isDnf;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function satisfiedBy(mixed $value): bool
    {
        if ($this->types === [['mixed']]) {
            return true;
        }

        if (($this->types === [['never']]) || ($this->types === [['void']])) {
            return false;
        }

        /** @infection-ignore-all */
        $key = $this->typeKeyForValue($value) . $this->id();
        if (isset(static::$matchCache[$key])) {
            return static::$matchCache[$key];
        }

        foreach ($this->types as $candidateTypes) {
            if ($this->satisfiedByIntersectionTypes($value, $candidateTypes)) {
                static::$matchCache[$key] = true;

                return true;
            }
        }

        static::$matchCache[$key] = false;

        return false;
    }

    /**
     * @param Type|string $compare
     * @return bool
     */
    public function matchedBy(Type|string $compare): bool
    {
        ($compare instanceof Type) or $compare = Type::byString($compare);

        if (($this->types === [['mixed']]) || ($compare->types === [['mixed']])) {
            return $this->types === [['mixed']];
        }

        if ($this->types === $compare->types) {
            return true;
        }

        /** @infection-ignore-all */
        $key = $compare->id() . $this->id();

        if (isset(static::$matchCache[$key])) {
            return static::$matchCache[$key];
        }

        foreach ($this->types as $refTypes) {
            if ($this->matchedByCompare($compare, $refTypes)) {
                static::$matchCache[$key] = true;

                return true;
            }
        }

        static::$matchCache[$key] = false;

        return false;
    }

    /**
     * @param Type|string $type
     * @return bool
     */
    public function isA(Type|string $type): bool
    {
        ($type instanceof Type) or $type = Type::byString($type);

        return $type->matchedBy($this);
    }

    /**
     * @param Type $compare
     * @return bool
     */
    public function equals(Type $compare): bool
    {
        return $compare->id() === $this->id();
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->satisfiedBy(null);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->id();
    }

    /**
     * @return array|null
     *
     * @codeCoverageIgnore
     */
    public function __debugInfo(): ?array
    {
        return ['type' => $this->id()];
    }

    /**
     * @return never
     *
     * @codeCoverageIgnore
     */
    public function __serialize(): array
    {
        throw new \Error(sprintf('Serialization of %s is not allowed.', __CLASS__));
    }

    /**
     * @param array $data
     * @return never
     *
     * @codeCoverageIgnore
     */
    public function __unserialize(array $data): void
    {
        throw new \Error(sprintf('Unserialization of %s is not allowed.', __CLASS__));
    }

    /**
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->id();
    }

    /**
     * @param Type $append
     * @return static
     */
    private function merge(Type $append): static
    {
        $instance = $this;
        foreach ($append->types as $types) {
            $types and $instance = $instance->addUnionTypeUnchecked(...$types);
        }

        return $instance;
    }

    /**
     * @param non-empty-string $type
     * @param non-empty-string ...$types
     * @return static
     *
     * @no-named-arguments
     */
    private function addUnionTypeUnchecked(string $type, string ...$types): static
    {
        /** @infection-ignore-all */
        return $this->addUnionType(false, $type, ...$types);
    }

    /**
     * @param non-empty-string $type
     * @param non-empty-string ...$types
     * @return static
     *
     * @no-named-arguments
     */
    private function addUnionTypeChecked(string $type, string ...$types): static
    {
        return $this->addUnionType(true, $type, ...$types);
    }

    /**
     * @param bool $check
     * @param non-empty-string $type
     * @param non-empty-string ...$types
     * @return static
     *
     * @no-named-arguments
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    private function addUnionType(bool $check, string $type, string ...$types): static
    {
        // phpcs:enable Inpsyde.CodeQuality.NestingLevel
        $isIntersection = $types !== [];

        $types[] = $type;

        if ($check) {
            if ($isIntersection && (array_unique($types) !== $types)) {
                $typesStr = implode('&', $types);
                throw new \Error("Duplicate type in intersection type '{$typesStr}'.");
            }
            $this->assertValidTypesToAdd($types);
        }

        $id = null;
        if ($isIntersection) {
            sort($types);
            $id = implode('&', $types);
        }
        $id ??= $type;

        if ($check && in_array($id, $this->ids, true)) {
            throw new \Error("Duplicate '{$id}' type in union type.");
        }

        if ($this->types) {
            $this->isDnf = $isIntersection || $this->isIntersection || $this->isDnf;
            $this->isUnion = !$this->isDnf;
            $this->isIntersection = false;
        } elseif ($isIntersection) {
            $this->isIntersection = true;
        }

        $this->ids[] = $id;
        $this->intersections[$id] = $isIntersection;
        $this->types and sort($this->ids);
        $this->types[] = $types;

        return $this;
    }

    /**
     * @param list<string> $types
     * @return void
     */
    private function assertValidTypesToAdd(array $types): void
    {
        $isUnion = $this->types !== [];

        // We are adding a union type, but if we already have types which must be standalone, or
        // if we are adding now some standalone types while having already some types, we bail.
        if (
            ($this->types === [['mixed']])
            || ($this->types === [['void']])
            || ($this->types === [['never']])
            || (
                $isUnion
                && (($types === ['mixed']) || ($types === ['void']) || ($types === ['never']))
            )
        ) {
            throw new \Error("'mixed', 'void', and 'never' only be used as standalone types.");
        }

        if (($this->types === [['resource']]) || ($isUnion && ($types === ['resource']))) {
            throw new \Error(
                "'resource' is a non-standard type only supported as a standalone type."
            );
        }

        $hasTrue = $this->hasTrue || ($isUnion && in_array(['true'], $this->types, true));
        $hasFalse = $this->hasFalse || ($isUnion && in_array(['false'], $this->types, true));
        if (
            ($hasTrue && in_array('false', $types, true))
            || ($hasFalse && in_array('true', $types, true))
        ) {
            throw new \Error('Type contains both true and false, bool should be used instead.');
        }
        /** @infection-ignore-all */
        $hasTrue and $this->hasTrue = true;
        /** @infection-ignore-all */
        $hasFalse and $this->hasFalse = true;

        // None of the built-in types can be used in intersection types, so if we have more than one
        // type in this intersection and at least one type is built-in, we bail.
        if (isset($types[1]) && array_intersect(self::DEFAULT_TYPES, $types)) {
            $invalidTypes = implode('&', $types);
            throw new \Error("'{$invalidTypes}' is not a valid intersection type");
        }
    }

    /**
     * @return string
     */
    private function id(): string
    {
        if ($this->calculatedId !== null) {
            return $this->calculatedId;
        }

        if (!isset($this->ids[1])) {
            return $this->ids[0];
        }

        $calculatedId = '';
        foreach ($this->ids as $id) {
            $wrappedId = $this->intersections[$id] ? "({$id})" : $id;
            $calculatedId .= ($calculatedId === '') ? $wrappedId : "|{$wrappedId}";
        }
        /** @var non-empty-string $calculatedId */
        $this->calculatedId = $calculatedId;

        return $this->calculatedId;
    }

    /**
     * @param mixed $value
     * @return string
     *
     * @infection-ignore-all
     */
    private function typeKeyForValue(mixed $value): string
    {
        if (($value === true) || ($value === false)) {
            return $value ? 'true' : 'false';
        }

        $typeKey = get_debug_type($value);
        if (str_ends_with($typeKey, '@anonymous')) {
            /** @var object $value */
            $typeKey .= '-' . base64_encode(get_class($value));
        } elseif (str_starts_with($typeKey, 'resource')) {
            $typeKey = 'resource';
        }

        return $typeKey;
    }

    /**
     * @param mixed $value
     * @param list<string> $intersectionTypes
     * @return bool
     */
    private function satisfiedByIntersectionTypes(mixed $value, array $intersectionTypes): bool
    {
        if (
            ($intersectionTypes === ['true'])
            || ($intersectionTypes === ['false'])
            || ($intersectionTypes === ['null'])
        ) {
            return $value === self::UNARY_TYPES_MAP[$intersectionTypes[0]];
        }

        if (in_array($intersectionTypes, self::ISSERS, true)) {
            /** @var callable(mixed):bool $callback */
            $callback = "is_{$intersectionTypes[0]}";

            return $callback($value);
        }

        foreach ($intersectionTypes as $type) {
            /** @var class-string $type */
            if (!is_a($value, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Type $compare
     * @param list<non-empty-string> $refTypes
     * @return bool
     *
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    private function matchedByCompare(Type $compare, array $refTypes): bool
    {
        $isIterable = $refTypes === ['iterable'];
        $isVirtual = $isIterable || ($refTypes === ['callable']) || ($refTypes === ['object']);
        /** @var 'iterable'|'callable'|'object'|null $virtualType */
        $virtualType = $isVirtual ? $refTypes[0] : null;

        // phpcs:enable Inpsyde.CodeQuality.NestingLevel
        foreach ($compare->types as $targetTypes) {
            if ($refTypes === $targetTypes) {
                continue;
            }

            if (
                ($refTypes === ['bool'])
                && (($targetTypes === ['true']) || ($targetTypes === ['false']))
            ) {
                continue;
            }

            if ($isIterable && ($targetTypes === ['array'])) {
                continue;
            }

            /** @psalm-suppress ArgumentTypeCoercion */
            if (
                in_array($refTypes, self::SIMPLE_REF_TYPES, true)
                || in_array($targetTypes, self::SIMPLE_TARGET_TYPES, true)
                || !$this->matchedByIntersectionTypes($refTypes, $targetTypes, $virtualType)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<non-empty-string> $refTypes
     * @param list<class-string> $intersectionTypes
     * @param 'iterable'|'callable'|'object'|null $virtualType
     * @return bool
     *
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    private function matchedByIntersectionTypes(
        array $refTypes,
        array $intersectionTypes,
        ?string $virtualType
    ): bool {
        // phpcs:enable Inpsyde.CodeQuality.NestingLevel

        foreach ($intersectionTypes as $intersectionType) {
            if ($virtualType === null) {
                if ($this->matchedByIntersectionObject($intersectionType, $refTypes)) {
                    return true;
                }
                continue;
            }

            if ($this->matchedByVirtualType($virtualType, $intersectionType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string $intersectionType
     * @param list<non-empty-string> $refTypes
     * @return bool
     */
    private function matchedByIntersectionObject(string $intersectionType, array $refTypes): bool
    {
        foreach ($refTypes as $refType) {
            /** @var class-string $refType */
            if (!is_a($intersectionType, $refType, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param 'iterable'|'callable'|'object' $refType
     * @param class-string $targetType
     * @return bool
     */
    private function matchedByVirtualType(string $refType, string $targetType): bool
    {
        /** @psalm-suppress DocblockTypeContradiction, ArgumentTypeCoercion */
        if ($refType === 'iterable') {
            return ($targetType === 'Traversable') || is_subclass_of($targetType, 'Traversable');
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if ($refType === 'callable') {
            return ($targetType === 'Closure') || method_exists($targetType, '__invoke');
        }

        return class_exists($targetType)
            || interface_exists($targetType)
            || enum_exists($targetType);
    }
}
