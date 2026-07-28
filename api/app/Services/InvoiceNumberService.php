<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Allocates gapless sequential invoice numbers (QL-2026-0001).
 *
 * Numbers are allocated at issue (draft -> sent), never at draft creation, so
 * abandoned drafts do not burn numbers. Allocation takes SELECT ... FOR UPDATE
 * on the per-year sequence row — max(id)+1 and count()+1 both race and both
 * leave gaps.
 */
class InvoiceNumberService
{
    public function allocate(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->year;
        $prefix = (string) config('billing.invoice_number_prefix');

        return DB::transaction(function () use ($prefix, $year) {
            $sequence = $this->lockedSequence($prefix, $year);

            $sequence->last_number += 1;
            $sequence->save();

            return sprintf('%s-%d-%04d', $prefix, $year, $sequence->last_number);
        });
    }

    /**
     * Fetch the sequence row under lock, creating it on first use of a year.
     * Two transactions can race the first-ever allocation; the unique
     * (prefix, year) index makes the loser's insert fail, after which it
     * re-selects the winner's row under lock.
     */
    private function lockedSequence(string $prefix, int $year): InvoiceSequence
    {
        $sequence = InvoiceSequence::query()
            ->where('prefix', $prefix)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence) {
            return $sequence;
        }

        try {
            InvoiceSequence::create([
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => 0,
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }
        }

        return InvoiceSequence::query()
            ->where('prefix', $prefix)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
