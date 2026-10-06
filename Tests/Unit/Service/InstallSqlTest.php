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

use CustomDelivery\Service\InstallSql;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstallSqlTest extends TestCase
{
    #[Test]
    public function theInstallScriptNoLongerDropsTheSlices(): void
    {
        $script = (string) file_get_contents(__DIR__.'/../../../Config/thelia.sql');

        $sql = InstallSql::keepingExistingTables($script);

        self::assertStringNotContainsString('DROP TABLE', $sql);
        self::assertSame(1, substr_count($sql, 'CREATE TABLE IF NOT EXISTS `custom_delivery_slice`'));
        self::assertSame(0, substr_count($sql, 'CREATE TABLE `'));
        self::assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $sql);
        self::assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1;', $sql);
        self::assertStringContainsString('CONSTRAINT `fk_area_id`', $sql);
    }

    #[Test]
    public function everythingElseOfTheScriptIsKept(): void
    {
        $sql = InstallSql::keepingExistingTables("SET FOREIGN_KEY_CHECKS = 0;\nDROP TABLE IF EXISTS `custom_delivery_slice`;\n\nCREATE TABLE `custom_delivery_slice`\n(\n    `id` INTEGER NOT NULL\n) ENGINE=InnoDB;\nSET FOREIGN_KEY_CHECKS = 1;\n");

        self::assertSame("SET FOREIGN_KEY_CHECKS = 0;\n\nCREATE TABLE IF NOT EXISTS `custom_delivery_slice`\n(\n    `id` INTEGER NOT NULL\n) ENGINE=InnoDB;\nSET FOREIGN_KEY_CHECKS = 1;\n", $sql);
    }
}
