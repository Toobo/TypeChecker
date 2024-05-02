<?php

declare(strict_types=1);

namespace Toobo\TypeChecker\Tests;

use PHPUnit\Framework;

abstract class TestCase extends Framework\TestCase
{
    /**
     * @return void
     */
    protected function requireDnfTypes(): void
    {
        if (PHP_VERSION_ID < 80200) {
            $this->markTestSkipped(sprintf('Test %s requires PHP 8.2+', __METHOD__));
        }
    }

    /**
     * @param \Closure $func
     * @return \ReflectionType
     */
    protected function extractTypeFromCallback(\Closure $func): \ReflectionType
    {
        $ref = (new \ReflectionFunction($func))->getParameters()[0]->getType();

        static::assertInstanceOf(\ReflectionType::class, $ref);

        return $ref;
    }
}
