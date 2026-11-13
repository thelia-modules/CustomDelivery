<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CustomDelivery\Tests\Unit\Service;

use CustomDelivery\Service\DecimalInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DecimalInputTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function typedNumbers(): iterable
    {
        yield 'a point is a decimal point' => ['5.90', 5.9];
        yield 'a comma is a decimal point' => ['5,90', 5.9];
        yield 'the stored value shown by the form is read back as it is' => ['9.9', 9.9];
        yield 'an integer' => ['12', 12.0];
        yield 'a number given as a float' => [9.9, 9.9];
        yield 'point as decimal, comma for thousands' => ['1,234.56', 1234.56];
        yield 'comma as decimal, point for thousands' => ['1.234,56', 1234.56];
        yield 'a repeated separator groups thousands' => ['1.234.567', 1234567.0];
        yield 'surrounding spaces are ignored' => [' 5,90 ', 5.9];
        yield 'zero' => ['0', 0.0];
    }

    #[Test]
    #[DataProvider('typedNumbers')]
    public function aTypedNumberKeepsItsDecimals(mixed $typed, float $expected): void
    {
        self::assertEqualsWithDelta($expected, DecimalInput::parse($typed), 0.00001);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notNumbers(): iterable
    {
        yield 'letters' => ['abc'];
        yield 'a negative number' => ['-5'];
        yield 'empty' => [''];
        yield 'only a separator' => ['.'];
        yield 'an array' => [['5']];
        yield 'null' => [null];
    }

    #[Test]
    #[DataProvider('notNumbers')]
    public function anythingElseGivesTheDefault(mixed $typed): void
    {
        self::assertSame(-1.0, DecimalInput::parse($typed));
        self::assertSame(7.0, DecimalInput::parse($typed, 7.0));
    }
}
