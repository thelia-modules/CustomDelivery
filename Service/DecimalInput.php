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

namespace CustomDelivery\Service;

/**
 * Reads a number typed in the slice form.
 *
 * A single separator, "." or ",", is the decimal one: "5.90" and "5,90" both give 5.9, and so does the "9.9" the
 * form shows for a stored 9.9. When both appear, the last one is the decimal separator and the other groups the
 * thousands ("1.234,56" and "1,234.56" give 1234.56). A separator repeated alone ("1.234.567") groups thousands.
 */
final class DecimalInput
{
    public static function parse(mixed $value, float $default = -1.0): float
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return $default;
        }

        $text = trim((string) $value);

        if (1 !== preg_match('/^[\d.,]+$/', $text)) {
            return $default;
        }

        $lastDot = strrpos($text, '.');
        $lastComma = strrpos($text, ',');

        if (false !== $lastDot && false !== $lastComma) {
            $decimalSeparator = $lastDot > $lastComma ? '.' : ',';
        } else {
            $separator = false !== $lastDot ? '.' : (false !== $lastComma ? ',' : null);
            $decimalSeparator = null !== $separator && 1 === substr_count($text, $separator) ? $separator : null;
        }

        $grouping = '.' === $decimalSeparator ? ',' : '.';
        $integerAndFraction = null === $decimalSeparator
            ? [str_replace(['.', ','], '', $text)]
            : explode($decimalSeparator, str_replace($grouping, '', $text));

        $number = $integerAndFraction[0].(isset($integerAndFraction[1]) ? '.'.$integerAndFraction[1] : '');

        return is_numeric($number) ? (float) $number : $default;
    }
}
