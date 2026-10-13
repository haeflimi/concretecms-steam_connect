<?php

namespace SteamConnect\Sync;

/**
 * INSERT … ON DUPLICATE KEY UPDATE for rows that several accounts share (apps, achievement definitions): the batch
 * steps of one sync can run in parallel workers, and a worker's cache of known rows goes stale, so a plain INSERT
 * of a row another step just added fails with a duplicate key.
 */
trait UpsertsRows
{
    /**
     * @param array<string, mixed> $key primary key columns
     * @param array<string, mixed> $values the other columns, updated when the row exists
     */
    protected function upsert(string $table, array $key, array $values): void
    {
        $columns = array_map([$this->db, 'quoteIdentifier'], array_merge(array_keys($key), array_keys($values)));
        $updates = array_map(function (string $column) {
            $column = $this->db->quoteIdentifier($column);

            return "$column = VALUES($column)";
        }, array_keys($values));
        $this->db->executeStatement(
            'INSERT INTO ' . $this->db->quoteIdentifier($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates),
            array_merge(array_values($key), array_values($values))
        );
    }
}
