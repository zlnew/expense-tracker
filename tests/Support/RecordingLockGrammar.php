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
 * Also records which tables are locked and which ids are locked together
 * in a single query, so a test can distinguish the transfer's dual-account
 * lock or the recurring row's lock from SyncBalance's single-row lock
 * (all three can run in the same flow).
 */
final class RecordingLockGrammar extends SQLiteGrammar
{
    public bool $lockRequested = false;

    /** @var list<string> tables locked via lockForUpdate(), in query order */
    public array $lockedTables = [];

    /** @var array<int, list<int>> id sets locked per query, in query order */
    public array $lockedIdSets = [];

    protected function compileLock(Builder $query, $value): string
    {
        $this->lockRequested = true;

        $table = is_array($query->from) ? $query->from[0] ?? null : $query->from;
        if ($table !== null) {
            $this->lockedTables[] = $table;
        }

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
