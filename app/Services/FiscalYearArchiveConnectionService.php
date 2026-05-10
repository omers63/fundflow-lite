<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * One SQLite archive file per fiscal year (configurable folder under the app base path).
 *
 * Registers a dynamic Laravel connection `{archive_fy_id}` targeting that file,
 * touches the empty file when missing, runs pending migrations once.
 */
final class FiscalYearArchiveConnectionService
{
    /**
     * Default directory under database_path().
     */
    public function archivesDirectoryAbsolute(): string
    {
        $extra = trim((string) config('fundflow.archive_fiscal_years_directory', 'archives'), '/');

        return database_path($extra !== '' ? $extra : 'archives');
    }

    public function archiveConnectionAlias(FiscalYear $fiscalYear): string
    {
        return 'archive_fy_'.$fiscalYear->id;
    }

    /**
     * Path relative to base_path(), normalized with forward slashes.
     */
    public function proposedRelativePath(FiscalYear $fiscalYear): string
    {
        $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $fiscalYear->code) ?? 'fy');

        return 'database/'.trim((string) config('fundflow.archive_fiscal_years_directory', 'archives'), '/')
            .'/fy_'.$fiscalYear->id.'_'.$code.'.sqlite';
    }

    /**
     * Resolves stored relative path or the proposed archive file (absolute filesystem path).
     */
    public function resolveAbsoluteArchivePath(FiscalYear $fiscalYear): string
    {
        if ($fiscalYear->archive_database_path) {
            $raw = trim($fiscalYear->archive_database_path);
            if ($raw === '') {
                return $this->toAbsoluteFromRelative($this->proposedRelativePath($fiscalYear));
            }

            if (Str::startsWith($raw, '/')) {
                return $raw;
            }

            return $this->toAbsoluteFromRelative($raw);
        }

        return $this->toAbsoluteFromRelative($this->proposedRelativePath($fiscalYear));
    }

    public function ensureArchiveDatabaseReady(FiscalYear $fiscalYear): string
    {
        return $this->registerSqliteFile(
            connectionName: $this->archiveConnectionAlias($fiscalYear),
            absolutePath: $this->resolveAbsoluteArchivePath($fiscalYear),
            runMigrate: true,
        );
    }

    /**
     * Register or refresh a SQLite connection pointing at `$absoluteSqlitePath` and optionally migrate.
     */
    public function registerSqliteFile(string $connectionName, string $absolutePath, bool $runMigrate): string
    {
        $directory = dirname($absolutePath);
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.basename($absolutePath);
        if (! File::exists($path)) {
            File::put($path, '');
        }

        config([
            'database.connections.'.$connectionName => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge($connectionName);
        DB::reconnect($connectionName);

        if ($runMigrate) {
            $code = Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
            ]);
            if ($code !== 0) {
                throw new RuntimeException('Archive migrations failed (exit '.$code.') for connection ['.$connectionName.']. '.trim(Artisan::output()));
            }
        }

        return $connectionName;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function registerStoredArchiveForRestore(FiscalYear $fiscalYear): string
    {
        $path = $fiscalYear->archive_database_path;
        if ($path === null || trim($path) === '') {
            throw new InvalidArgumentException(
                'Fiscal year '.$fiscalYear->code.' has no archive_database_path; cannot restore.'
            );
        }

        return $this->registerSqliteFile(
            connectionName: $this->archiveConnectionAlias($fiscalYear),
            absolutePath: $this->resolveAbsoluteArchivePath($fiscalYear),
            runMigrate: false,
        );
    }

    /**
     * Store the SQLite path (relative to base_path) once for this fiscal year.
     */
    public function persistArchivePathIfMissing(FiscalYear $fiscalYear): void
    {
        $current = $fiscalYear->archive_database_path;
        if ($current !== null && trim((string) $current) !== '') {
            return;
        }

        $fiscalYear->update([
            'archive_database_path' => $this->proposedRelativePath($fiscalYear),
        ]);
    }

    private function toAbsoluteFromRelative(string $relative): string
    {
        return base_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/')));
    }
}
