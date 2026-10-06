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
 * Makes the generated install script safe to run on a database that already holds the slices.
 *
 * The activation runs Config/thelia.sql whenever the is_initialized flag is missing, which is also the case of a
 * database carried over from an older Thelia: the DROP TABLE of the generated script emptied the shipping slices.
 * The table is now created only when it does not exist; removing it stays the job of destroy().
 */
final class InstallSql
{
    public static function keepingExistingTables(string $sql): string
    {
        $sql = preg_replace('/^[ \t]*DROP TABLE IF EXISTS `\w+`;[ \t]*\R/m', '', $sql) ?? $sql;

        return preg_replace('/^(\s*)CREATE TABLE `/m', '$1CREATE TABLE IF NOT EXISTS `', $sql) ?? $sql;
    }
}
