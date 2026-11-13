<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class DatabaseBackupService
{
    public function writeTo($output): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Backup SQL saat ini hanya mendukung koneksi MySQL.');
        }

        if (! is_resource($output)) {
            throw new RuntimeException('Stream tujuan backup tidak valid.');
        }

        $this->write($output, "-- Backup Sistem Manajemen AC\n-- Generated at ".now()->toDateTimeString()."\n\n");
        $this->write($output, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query('SHOW TABLES');

        if ($tables === false) {
            throw new RuntimeException('Daftar tabel database tidak dapat dibaca.');
        }

        while (($tableRow = $tables->fetch(PDO::FETCH_NUM)) !== false) {
            $tableName = (string) $tableRow[0];
            $quotedTable = $this->quoteIdentifier($tableName);
            $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}");
            $createRow = $create?->fetch(PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? null;

            if (! is_string($createSql)) {
                throw new RuntimeException("Struktur tabel {$tableName} tidak dapat dibaca.");
            }

            $this->write($output, "DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

            $rows = $pdo->query("SELECT * FROM {$quotedTable}");

            if ($rows === false) {
                throw new RuntimeException("Data tabel {$tableName} tidak dapat dibaca.");
            }

            while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns = array_map(fn (string $column): string => $this->quoteIdentifier($column), array_keys($row));
                $values = array_map(fn (mixed $value): string => $this->quoteValue($pdo, $value), array_values($row));

                $this->write(
                    $output,
                    "INSERT INTO {$quotedTable} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n",
                );
            }

            $this->write($output, "\n");
        }

        $this->write($output, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);

            if ($value === false) {
                throw new RuntimeException('Nilai binary database tidak dapat dibaca.');
            }
        }

        $quoted = $pdo->quote((string) $value, PDO::PARAM_STR);

        if ($quoted === false) {
            throw new RuntimeException('Nilai database tidak dapat di-escape untuk file backup.');
        }

        return $quoted;
    }

    /**
     * @param  resource  $output
     */
    private function write($output, string $contents): void
    {
        if (fwrite($output, $contents) === false) {
            throw new RuntimeException('Penulisan file backup SQL gagal.');
        }
    }
}
