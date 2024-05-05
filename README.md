# Type Checker

[![Quality Assurance](https://github.com/Toobo/TypeChecker/actions/workflows/quality-assurance.yml/badge.svg)](https://github.com/Toobo/TypeChecker/actions/workflows/quality-assurance.yml)

## What is this

You can think of it as a way to build an [`is_a()`](https://www.php.net/manual/it/function.is-a.php) function on steroids.

`is_a()` only works for classes, interfaces and enums. It does not work for scalars (`string`, `int`, etc., including unary types like `true` and `false`) and it does not work for virtual types (`iterable`, `callable`).

Moreover, it does not work for complex types, such as unions, intersections, and DNF.

If you ever wanted to do something like:

```php
is_a('iterable|MyThing|(Iterator&Countable)|null', $thing);
```

Then this package is for you. But there's more.

`is_a()` accepts a type string, but sometimes one wants to check against a [`ReflectionType`](https://www.php.net/manual/it/class.reflectiontype.php), or even a [`ReflectionClass`](https://www.php.net/manual/it/class.reflectionclass.php) and this package can do those things as well.

An example **with a string**:

```php
use Toobo\TypeChecker\Type;

Type::byString('iterable|MyThing|(Iterator&Countable)|null')->satisfiedBy($thing);
```

with a **`ReflectionType`**:

```php
use Toobo\TypeChecker\Type;

function test(iterable|MyThing|(Iterator&Countable)|null $param) {}
$refType = (new ReflectionFunction('test'))->getParameters()[0]->getType();

Type::byReflectionType($refType)->satisfiedBy($thing);
```

and with a **`ReflectionClass`**:

```php
use Toobo\TypeChecker\Type;

Type::byReflectionType(new ReflectionClass($this))->satisfiedBy($this);
```



## A deeper look



### Named constructors

Besides by strings and by reflection, the `Type` class can also be instantiated using named constructors such as `Type::string()`, `Type::resource()`, or `Type::mixed()`, but also `Type::iterable()`, `Type::callable()` or even `Type::null()`, `Type::true()`, and `Type::false()`.

For completeness' sake, it is also possible to create instances for types that will never match any value, like `Type::void()` or `Type::never()`.



### Matching types

The `Type` class aims at representing the entire PHP type system. And it is possible to compare one instance with another:

```php
assert(Type::byString('IteratorAggregate&Countable')->matchedBy('ArrayObject'));
assert(Type::byString('IteratorAggregate&Countable')->matchedBy(Type::byString('ArrayObject')));

assert(Type::byString('ArrayObject')->isA('IteratorAggregate&Countable'));
assert(Type::byString('ArrayObject')->isA(Type::byString('IteratorAggregate&Countable')));
```

`Type::matchedBy()` and `Type::isA()` both accept a string or another type instance and check type compliance. They are the inverse of each other.

`Type::matchedBy()` behavior can be described as: _if a function's argument type is represented by the type calling the method, would it be satisfied by a value whose type is represented by the type passed as argument_?

`Type::isA()` behavior can be described as: _if a function's argument type is represented by the type passed as argument, would it be satisfied by a value whose type is represented by the instance calling the method_?



### Type information

The `Type` class has several methods to get information about the PHP type it represents.

- `isStandalone()`
- `isUnion()`
- `isIntersection()`
- `isDnf()`

can tell what kind of composite type is, or if not composed at all.

There's also a `isNullable()` method.



### Type position utils

PHP allows type declarations in three places:

- Function arguments types
- Function return
- Properties declaration

And a slightly different set of types is supported in the three positions.

For example, `void` and `never` can only be used as return types, and `callable` can not be used as property type.

The `Type` class has three methods: `Type::isPropertySafe()`, `Type::isArgumentSafe()`, and `Type::isReturnSafe()` which can be used to determine if the instance represents a type that can be used in the three positions.



## Comparison with other libraries

There are other libraries out there that deals with "objects representing types".

- [PHPDoc Parser for PHPStan](https://github.com/phpstan/phpdoc-parser) is an amazing type parser  from doc bloc. While it is similar to this package about creating "type objects" from strings,
  the PHPStan package more powerful supporting [much more than the PHP native type system](https://phpstan.org/writing-php-code/phpdoc-types).
  However, it does not support the possibility to check if a value belongs to that type, which is this package's main reason to exist.
  
- [PHP Documentor's TypeResolver](https://github.com/phpDocumentor/TypeResolver) is based on the PHPStan's package mentioned above. And the same differences highlighted above apply.
  
- [Symfony type-info component](https://github.com/symfony/type-info). The "new guy" on the scene, still experimental. It is also based on the PHPStan library for string parsing, but similarly
  to this library also deals with reflection types. At the moment of writing, the possibility to check a value satisfy a type is only limited to native "standalone" types, which includes virtual types such as `iterable` and `callable`, but does not include composed types.

In general, this package dependency-free, simpler in only targeting PHP-supported type rather than "advanced" types that are used in documentation and static analysis. This limited scope allows for much simpler code in a single class. Moreover, this library focuses on _checking_ type of values more than "parsing" or "extracting" types from strings, like other libraries.