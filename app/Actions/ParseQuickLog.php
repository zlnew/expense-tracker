<?php

namespace App\Actions;

use App\Models\Balance;
use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Parses a free-text quick-log line into transaction fields.
 *
 * Grammar: "<note> <amount> [<balance>]"
 * - amount: handles `k`/`rb`/`ribu`/plain (e.g. 33k => 33000, 1.5k => 1500, 2rb => 2000).
 * - category: fuzzy-matched against existing user categories; deterministic
 *             fallback to last-used on ambiguity/no match (never invents one).
 * - balance: optional fuzzy match by name; defaults to lastUsedBalanceId else primary.
 *
 * Pure helper — no DB writes. Safe for Pest Unit tests.
 */
class ParseQuickLog extends Action
{
    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Balance>  $balances
     * @return array{category_id: int|null, amount: int|null, balance_id: int|null, note: string, type: string|null}
     */
    public function __construct(
        private readonly string $raw,
        private readonly Collection $categories,
        private readonly Collection $balances,
        private readonly ?int $lastUsedCategoryId = null,
        private readonly ?int $lastUsedBalanceId = null,
        private readonly ?int $primaryBalanceId = null,
    ) {}

    public function handle(): array
    {
        $raw = trim($this->raw);

        if ($raw === '') {
            return $this->result(null, null, null, '', null);
        }

        $tokens = preg_split('/\s+/', $raw) ?: [];
        $amountTokenIndex = $this->findAmountTokenIndex($tokens);

        if ($amountTokenIndex === null) {
            // No amount found — treat whole input as note, resolve category/balance via fallback
            $note = $raw;

            return $this->result(
                $this->resolveCategory($note),
                null,
                $this->resolveBalanceFromTail($tokens, $amountTokenIndex),
                $note,
                $this->inferType($this->resolveCategory($note)),
            );
        }

        $amount = $this->parseAmount($tokens[$amountTokenIndex]);

        // Note = everything before the amount token (joined)
        $noteTokens = array_slice($tokens, 0, $amountTokenIndex);
        $note = trim(implode(' ', $noteTokens));

        // Balance = optional tail token(s) after amount, fuzzy-matched
        $balanceId = $this->resolveBalanceFromTail($tokens, $amountTokenIndex);
        $categoryId = $this->resolveCategory($note);

        // If balance was resolved from tail, strip that tail word from note? No — note
        // stays as the pre-amount text. Balance hint is consumed separately.
        return $this->result($categoryId, $amount, $balanceId, $note, $this->inferType($categoryId));
    }

    private function result(?int $categoryId, ?int $amount, ?int $balanceId, string $note, ?string $type): array
    {
        return [
            'category_id' => $categoryId,
            'amount' => $amount,
            'balance_id' => $balanceId,
            'note' => $note,
            'type' => $type,
        ];
    }

    private function findAmountTokenIndex(array $tokens): ?int
    {
        foreach ($tokens as $i => $tok) {
            if ($this->isAmountToken($tok)) {
                return $i;
            }
        }

        return null;
    }

    private function isAmountToken(string $tok): bool
    {
        // 33k, 1.5k, 2rb, 1,500rb, 33K, 50000, 1.000, 1,000, 1.5rb etc.
        // Normalize: strip commas/dots used as thousands separators before check.
        $n = strtolower(trim($tok));

        return (bool) preg_match('/^\d[\d.,]*\s*(k|rb|ribu)?$/i', $n);
    }

    private function parseAmount(string $tok): ?int
    {
        $n = strtolower(trim($tok));

        $mult = 1;
        if (str_ends_with($n, 'ribu')) {
            $mult = 1000;
            $n = substr($n, 0, -4);
        } elseif (str_ends_with($n, 'rb')) {
            $mult = 1000;
            $n = substr($n, 0, -2);
        } elseif (str_ends_with($n, 'k')) {
            $mult = 1000;
            $n = substr($n, 0, -1);
        }

        // Remove thousands separators (both . and ,), but keep decimal point.
        // Strategy: if both separators present, the last one is decimal; otherwise
        // dots are thousands separators in id-ID. Keep it simple: strip commas,
        // strip dots that are thousands separators, keep decimal dot.
        $n = trim($n);
        // Remove commas used as thousands separators
        $n = str_replace(',', '', $n);

        // Handle id-ID thousands dots: "1.000" or "1.500.000" with optional decimal.
        // If n contains dots, the last dot with 1-2 digits after it could be decimal;
        // anything else is thousands. Simpler: if amount has a decimal meaning (1.5k),
        // that dot is decimal. If it's 1.000k-like, dots are thousands.
        // Decision: when mult > 1 and n contains '.', keep one decimal dot only.
        if (str_contains($n, '.')) {
            $parts = explode('.', $n);
            if (count($parts) === 2 && strlen($parts[1]) <= 2 && $mult > 1) {
                // Likely decimal (1.5k)
                $n = $parts[0].'.'.$parts[1];
            } else {
                // Thousands separators — strip all dots
                $n = str_replace('.', '', $n);
            }
        }

        if ($n === '' || ! is_numeric($n)) {
            return null;
        }

        $val = (float) $n * $mult;

        return (int) round($val);
    }

    private function resolveCategory(string $note): ?int
    {
        if ($this->categories->isEmpty()) {
            return $this->lastUsedCategoryId;
        }

        if ($note === '') {
            return $this->fallbackCategory();
        }

        $needle = strtolower(trim($note));

        // Exact name match (case-insensitive)
        foreach ($this->categories as $c) {
            if (strtolower($c->name) === $needle) {
                return $c->id;
            }
        }

        // Substring / alias match: token appears inside category name OR vice versa.
        // "bensin" should hit "Transportation" via alias table; otherwise substring.
        $aliasHits = $this->aliasCategory($needle);
        if ($aliasHits !== null) {
            return $aliasHits;
        }

        $candidates = [];
        foreach ($this->categories as $c) {
            $lname = strtolower($c->name);
            if (str_contains($lname, $needle) || str_contains($needle, $lname)) {
                $candidates[] = $c;
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0]->id;
        }

        if (count($candidates) > 1) {
            // Ambiguous substring — deterministic fallback to last-used, per spec §12
            return $this->fallbackCategory();
        }

        // No match → last-used fallback (do not invent)
        return $this->fallbackCategory();
    }

    private function aliasCategory(string $needle): ?int
    {
        // Small deterministic alias map for common Indonesian quick-log shorthand.
        // Keys are lowercased substrings the user may type; values are canonical
        // DefaultCategories names. Only resolves if the target category exists.
        $aliases = [
            'bensin' => 'Transportation',
            'bensin ' => 'Transportation',
            'parkir' => 'Transportation',
            'tol' => 'Transportation',
            'ojek' => 'Transportation',
            'grab' => 'Transportation',
            'gojek' => 'Transportation',
            'makan' => 'Food',
            'kopi' => 'Food',
            'jajan' => 'Food',
            'ngopi' => 'Food',
            'listrik' => 'Utilities',
            'air' => 'Utilities',
            'wifi' => 'Utilities',
            'pulsa' => 'Utilities',
            'sewa' => 'Home',
            'kos' => 'Home',
            'obat' => 'Health',
            'dokter' => 'Health',
            'kado' => 'Gifts',
            'hadiah' => 'Gifts',
            'servis' => 'Maintenance',
            'service' => 'Maintenance',
            'pajak' => 'Taxes',
            'gaji' => 'Paycheck',
            'bonus' => 'Bonus',
            'bunga' => 'Interest',
            'tabungan' => 'Savings',
        ];

        foreach ($aliases as $key => $canonical) {
            if (str_contains($needle, $key) || str_contains($key, $needle)) {
                $hit = $this->categories->firstWhere('name', $canonical);
                if ($hit) {
                    return $hit->id;
                }
            }
        }

        return null;
    }

    private function fallbackCategory(): ?int
    {
        if ($this->lastUsedCategoryId !== null) {
            $exists = $this->categories->firstWhere('id', $this->lastUsedCategoryId);
            if ($exists) {
                return $this->lastUsedCategoryId;
            }
        }

        return null;
    }

    private function inferType(?int $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }
        $cat = $this->categories->firstWhere('id', $categoryId);

        return $cat?->type?->value ?? (is_string($cat?->type) ? $cat->type : null);
    }

    private function resolveBalanceFromTail(array $tokens, ?int $amountIdx): ?int
    {
        if ($this->balances->isEmpty()) {
            return $this->lastUsedBalanceId ?? $this->primaryBalanceId;
        }

        // Try to match the tail word after amount as a balance name
        if ($amountIdx !== null && $amountIdx + 1 < count($tokens)) {
            $tail = strtolower(trim(implode(' ', array_slice($tokens, $amountIdx + 1))));

            // Exact
            foreach ($this->balances as $b) {
                if (strtolower($b->name) === $tail) {
                    return $b->id;
                }
            }

            // Substring single-hit else fallback (deterministic)
            $cands = [];
            foreach ($this->balances as $b) {
                $lname = strtolower($b->name);
                if (str_contains($lname, $tail) || str_contains($tail, $lname)) {
                    $cands[] = $b;
                }
            }
            if (count($cands) === 1) {
                return $cands[0]->id;
            }
        }

        // No explicit balance in text → last-used or primary
        if ($this->lastUsedBalanceId !== null) {
            $exists = $this->balances->firstWhere('id', $this->lastUsedBalanceId);
            if ($exists) {
                return $this->lastUsedBalanceId;
            }
        }

        return $this->primaryBalanceId;
    }
}
