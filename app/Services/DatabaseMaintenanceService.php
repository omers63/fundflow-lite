<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseMaintenanceService
{
    /**
     * Never truncated: migrations, auth/session/cache queues, Spatie permission tables.
     *
     * @return list<string>
     */
    public function alwaysExcludedTableNames(): array
    {
        $permissionTables = array_values(array_unique(array_filter([
            config('permission.table_names.permissions'),
            config('permission.table_names.roles'),
            config('permission.table_names.model_has_permissions'),
            config('permission.table_names.model_has_roles'),
            config('permission.table_names.role_has_permissions'),
        ])));

        return array_values(array_unique(array_merge([
            'migrations',
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ], $permissionTables)));
    }

    /**
     * Unique table names for the default connection (SQLite can return duplicates from schema listing).
     *
     * @return list<string>
     */
    private function databaseTableNames(): array
    {
        return array_values(array_unique(
            Schema::getTableListing(schema: null, schemaQualified: false)
        ));
    }

    /**
     * Tables that will be emptied: exist, are not always excluded, and have no soft-delete column.
     *
     * @return list<string>
     */
    public function getPurgeableTables(): array
    {
        $excluded = $this->alwaysExcludedTableNames();
        $names = $this->databaseTableNames();

        $purgeable = [];
        foreach ($names as $table) {
            if (in_array($table, $excluded, true)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            $purgeable[] = $table;
        }
        $purgeable = array_values(array_unique($purgeable));
        sort($purgeable);

        return $purgeable;
    }

    /**
     * Tables skipped only because they define soft deletes.
     *
     * @return list<string>
     */
    public function getTablesSkippedForSoftDeletes(): array
    {
        $excluded = $this->alwaysExcludedTableNames();
        $names = $this->databaseTableNames();
        $skipped = [];

        foreach ($names as $table) {
            if (in_array($table, $excluded, true)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                $skipped[] = $table;
            }
        }
        $skipped = array_values(array_unique($skipped));
        sort($skipped);

        return $skipped;
    }

    /**
     * Truncate all purgeable tables (FK checks disabled for the operation).
     *
     * @return int Number of tables truncated
     */
    public function purgePurgeableTables(): int
    {
        $tables = $this->getPurgeableTables();
        if ($tables === []) {
            return 0;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return count($tables);
    }

    public function downloadBackupResponse(): BinaryFileResponse|StreamedResponse
    {
        $connection = Config::get('database.default');
        $driver = Config::get("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => $this->downloadSqliteBackup(),
            'mysql', 'mariadb' => $this->downloadMysqlBackup($connection),
            default => throw new RuntimeException(
                "Database backup is not implemented for driver [{$driver}]. Use SQLite or MySQL."
            ),
        };
    }

    private function downloadSqliteBackup(): BinaryFileResponse
    {
        $path = Config::get('database.connections.sqlite.database');

        if ($path === ':memory:') {
            throw new RuntimeException('Cannot download an in-memory SQLite database.');
        }

        if (!is_string($path) || $path === '') {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (!str_starts_with($path, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $path)) {
            $path = base_path($path);
        }

        $path = realpath($path) ?: $path;

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('SQLite database file is missing or not readable.');
        }

        $filename = 'fundflow-backup-' . now()->format('Y-m-d-His') . '.sqlite';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function downloadMysqlBackup(string $connection): StreamedResponse
    {
        $config = Config::get("database.connections.{$connection}");

        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        if ($database === '') {
            throw new RuntimeException('MySQL database name is not configured.');
        }

        $mysqldump = $this->findMysqldumpBinary();
        if ($mysqldump === null) {
            throw new RuntimeException(
                'The mysqldump executable was not found in your PATH. Install MySQL client tools or use SQLite for one-click backup.'
            );
        }

        $filename = 'fundflow-backup-' . now()->format('Y-m-d-His') . '.sql';

        return response()->streamDownload(function () use ($mysqldump, $host, $port, $database, $username, $password): void {
            $result = Process::env(['MYSQL_PWD' => $password])
                ->timeout(600)
                ->run([
                    $mysqldump,
                    '--host=' . $host,
                    '--port=' . $port,
                    '--user=' . $username,
                    '--single-transaction',
                    '--no-tablespaces',
                    '--routines',
                    '--add-drop-table',
                    $database,
                ]);

            if (!$result->successful()) {
                throw new RuntimeException(
                    'mysqldump failed: ' . ($result->errorOutput() ?: $result->output())
                );
            }

            echo $result->output();
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    private function findMysqldumpBinary(): ?string
    {
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['mysqldump.exe', 'mysqldump']
            : ['mysqldump'];

        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '');

        foreach ($candidates as $name) {
            foreach ($paths as $dir) {
                if ($dir === '') {
                    continue;
                }
                $full = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if (is_file($full) && is_executable($full)) {
                    return $full;
                }
            }
        }

        foreach ($candidates as $name) {
            $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
            $check = Process::run([$which, $name]);
            if ($check->successful()) {
                $line = trim(explode("\n", $check->output())[0] ?? '');
                if ($line !== '' && is_file($line)) {
                    return $line;
                }
            }
        }

        return null;
    }
}
