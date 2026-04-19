<?php

namespace App\Support;

final class CsvStringParser
{
    /**
     * Parse UTF-8 CSV text into rows. Uses {@see fgetcsv} on a memory stream so quoted fields
     * (e.g. "SAR 98,170.00") stay aligned; splitting on newlines with {@see str_getcsv} breaks that.
     *
     * @return list<list<string|null>>
     */
    public static function parseRows(string $utf8CsvContent, string $delimiter): array
    {
        $delimiter = $delimiter === '\t' ? "\t" : $delimiter;

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open memory stream for CSV parsing.');
        }

        fwrite($handle, $utf8CsvContent);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (self::rowIsBlank($row)) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $row
     */
    private static function rowIsBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
