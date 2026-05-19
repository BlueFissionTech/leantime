<?php

namespace Leantime\Domain\DataIntegrityTools\Services;

use PDO;
use RuntimeException;

class PdoDatabaseDumpService
{
    public function dump(PDO $pdo, string $destinationPath): void
    {
        $handle = fopen($destinationPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not open backup file for writing: '.$destinationPath);
        }

        try {
            fwrite($handle, "-- Leantime fallback SQL dump\n");
            fwrite($handle, '-- Generated: '.date('c')."\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($this->getTables($pdo) as $table) {
                fwrite($handle, sprintf("DROP TABLE IF EXISTS `%s`;\n", $table));
                fwrite($handle, $this->getCreateTableSql($pdo, $table).";\n\n");

                $this->writeInserts($pdo, $handle, $table);
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getTables(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn (array $row): string => (string) $row[0], $rows);
    }

    private function getCreateTableSql(PDO $pdo, string $table): string
    {
        $row = $pdo->query(sprintf('SHOW CREATE TABLE `%s`', $table))->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            throw new RuntimeException('Could not fetch CREATE TABLE statement for '.$table);
        }

        foreach (['Create Table', 'Create View'] as $key) {
            if (isset($row[$key])) {
                return (string) $row[$key];
            }
        }

        throw new RuntimeException('Unexpected SHOW CREATE TABLE response for '.$table);
    }

    /**
     * @param  resource  $handle
     */
    private function writeInserts(PDO $pdo, $handle, string $table): void
    {
        $statement = $pdo->query(sprintf('SELECT * FROM `%s`', $table));

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(
                static fn (string $column): string => sprintf('`%s`', $column),
                array_keys($row)
            );
            $values = array_map(
                fn ($value): string => $this->quoteValue($pdo, $value),
                array_values($row)
            );

            fwrite(
                $handle,
                sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s);\n",
                    $table,
                    implode(', ', $columns),
                    implode(', ', $values)
                )
            );
        }
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }
}
