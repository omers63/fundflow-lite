<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Portable SQL fragments for MySQL/MariaDB vs SQLite (and other drivers).
 * Use when raw SELECT expressions must differ by driver; prefer Eloquent date
 * methods (whereYear / whereMonth) when possible — they are already portable.
 */
final class DatabaseDialect
{
    public static function driverName(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    public static function isMysqlFamily(): bool
    {
        return in_array(self::driverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * Expression for calendar year extracted from a date/datetime column (for SELECT / GROUP BY).
     */
    public static function yearExpression(string $column): string
    {
        return match (self::driverName()) {
            'mysql', 'mariadb' => "YEAR({$column})",
            'pgsql' => "CAST(EXTRACT(YEAR FROM {$column}) AS INTEGER)",
            default => "CAST(strftime('%Y', {$column}) AS INTEGER)",
        };
    }

    /**
     * Expression for calendar month (1–12) extracted from a date/datetime column.
     */
    public static function monthExpression(string $column): string
    {
        return match (self::driverName()) {
            'mysql', 'mariadb' => "MONTH({$column})",
            'pgsql' => "CAST(EXTRACT(MONTH FROM {$column}) AS INTEGER)",
            default => "CAST(strftime('%m', {$column}) AS INTEGER)",
        };
    }
}
