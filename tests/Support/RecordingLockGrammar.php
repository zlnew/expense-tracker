<?php

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * Laravel 13's SQLiteGrammar::compileLock() returns '' — sqlite strips
 * FOR UPDATE from the emitted SQL entirely (and would reject it at
 * execution). To prove a lock is *requested*, record compileLock calls
 * instead of grepping the query log for a clause that can never appear.
 *
 * Also records which ids are locked together in a single query so a test
 * can tell the transfer's dual-account lock apart from SyncBalance's
 * single-row lock (both run during a transfer).
 */
final class RecordingLockGrammar extends SQLiteGrammar
{
    public bool $lockRequested = false;

    /** @var array<int, list<int>> id sets locked per query, in query order */
    public array $lockedIdSets = [];

    protected function compileLock(Builder $query, $value): string
    {
        $this->lockRequested = true;

        $ids = $this->extractWhereInIds($query);
        if ($ids !== []) {
            $this->lockedIdSets[] = $ids;
        }

        return parent::compileLock($query, $value);
    }

    /**
     * @return list<int>
     */
    private function extractWhereInIds(Builder $query): array
    {
        $ids = [];

        foreach ($query->wheres as $where) {
            if (($where['type'] ?? null) === 'In' && isset($where['values'])) {
                foreach ($where['values'] as $value) {
                    if (is_numeric($value)) {
                        $ids[] = (int) $value;
                    }
                }
            }
        }

        sort($ids);

        return $ids;
    }
}
