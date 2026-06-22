<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountingService
{
    public function createDraft(array $header, array $lines): int
    {
        return DB::transaction(fn (): int => $this->persist($header, $lines, 'draft'));
    }

    public function createPosted(array $header, array $lines): int
    {
        return DB::transaction(function () use ($header, $lines): int {
            if (! empty($header['source_type']) && ! empty($header['source_id'])) {
                $existing = DB::table('accounting_journals')
                    ->where('source_type', $header['source_type'])
                    ->where('source_id', $header['source_id'])
                    ->value('id');
                if ($existing) {
                    return (int) $existing;
                }
            }

            $this->assertPeriodOpen($header['branch_id'] ?? null, (string) $header['journal_date']);

            return $this->persist($header, $lines, 'posted');
        });
    }

    public function postDraft(int $journalId, ?int $userId = null): int
    {
        return DB::transaction(function () use ($journalId, $userId): int {
            $journal = DB::table('accounting_journals')->lockForUpdate()->find($journalId);
            if (! $journal || $journal->status !== 'draft') {
                throw new DomainException(__('accounting.errors.only_drafts_posted'));
            }

            $this->assertPeriodOpen($journal->branch_id, $journal->journal_date);
            $lines = DB::table('accounting_journal_lines')
                ->where('accounting_journal_id', $journalId)
                ->get()
                ->map(fn ($line): array => [
                    'account_id' => $line->accounting_account_id,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                ])->all();
            [$normalized, $debit, $credit] = $this->normalizeLines($lines);

            DB::table('accounting_journals')->where('id', $journalId)->update([
                'status' => 'posted',
                'total_debit' => $debit,
                'total_credit' => $credit,
                'posted_at' => now(),
                'created_by' => $userId ?: $journal->created_by,
                'updated_at' => now(),
            ]);
            $this->audit('accounting.journal.posted', 'accounting_journal', $journalId, $journal->branch_id, ['status' => 'draft'], ['status' => 'posted']);

            return $journalId;
        });
    }

    public function updateDraft(int $journalId, array $header, array $lines, ?int $userId = null): int
    {
        return DB::transaction(function () use ($journalId, $header, $lines, $userId): int {
            $journal = DB::table('accounting_journals')->lockForUpdate()->find($journalId);
            if (! $journal || $journal->status !== 'draft') {
                throw new DomainException(__('accounting.errors.only_drafts_edited'));
            }

            $branchId = $header['branch_id'] ?? $journal->branch_id;
            $journalDate = (string) ($header['journal_date'] ?? $journal->journal_date);
            $this->assertPeriodOpen($branchId, $journalDate);
            [$normalized, $debit, $credit] = $this->normalizeLines($lines);
            $before = [
                'journal_date' => $journal->journal_date,
                'branch_id' => $journal->branch_id,
                'description' => $journal->description,
                'total_debit' => $journal->total_debit,
                'total_credit' => $journal->total_credit,
            ];
            $now = now();

            DB::table('accounting_journals')->where('id', $journalId)->update([
                'company_id' => $header['company_id'] ?? $journal->company_id,
                'branch_id' => $branchId,
                'journal_date' => $journalDate,
                'description' => $header['description'] ?? $journal->description,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'created_by' => $userId ?: $journal->created_by,
                'updated_at' => $now,
            ]);
            DB::table('accounting_journal_lines')->where('accounting_journal_id', $journalId)->delete();

            foreach ($normalized as $line) {
                DB::table('accounting_journal_lines')->insert([
                    'accounting_journal_id' => $journalId,
                    'accounting_account_id' => $line['account_id'],
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'party_type' => $line['party_type'],
                    'party_id' => $line['party_id'],
                    'source_type' => $journal->source_type,
                    'source_id' => $journal->source_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit('accounting.journal.updated', 'accounting_journal', $journalId, $branchId, $before, [
                'journal_date' => $journalDate,
                'branch_id' => $branchId,
                'description' => $header['description'] ?? $journal->description,
                'total_debit' => $debit,
                'total_credit' => $credit,
            ]);

            return $journalId;
        });
    }

    public function reverse(int $journalId, string $date, string $description, ?int $userId = null): int
    {
        return DB::transaction(function () use ($journalId, $date, $description, $userId): int {
            $original = DB::table('accounting_journals')->lockForUpdate()->find($journalId);
            if (! $original || $original->status !== 'posted') {
                throw new DomainException(__('accounting.errors.only_posted_reversed'));
            }
            if (DB::table('accounting_journals')->where('reversal_of_id', $journalId)->exists()) {
                throw new DomainException(__('accounting.errors.already_reversed'));
            }

            $lines = DB::table('accounting_journal_lines')
                ->where('accounting_journal_id', $journalId)
                ->get()
                ->map(fn ($line): array => [
                    'account_id' => $line->accounting_account_id,
                    'description' => $description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                ])->all();

            $reversalId = $this->createPosted([
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'journal_date' => $date,
                'source_type' => 'reversal',
                'source_id' => $journalId,
                'reversal_of_id' => $journalId,
                'description' => $description,
                'created_by' => $userId,
            ], $lines);

            DB::table('accounting_journals')->where('id', $journalId)->update([
                'status' => 'reversed',
                'updated_at' => now(),
            ]);
            $this->audit('accounting.journal.reversed', 'accounting_journal', $journalId, $original->branch_id, ['status' => 'posted'], ['status' => 'reversed', 'reversal_id' => $reversalId]);

            return $reversalId;
        });
    }

    public function closeDaily(int $companyId, int $branchId, string $date, float $actualCash, ?string $notes, ?int $userId): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $date, $actualCash, $notes, $userId): int {
            $this->assertClosable($branchId, $date, $date);
            $payments = DB::table('sales_payments')
                ->where('branch_id', $branchId)
                ->whereDate('paid_at', $date)
                ->where('status', 'posted');
            $sum = fn (string $method): float => round((float) (clone $payments)->where('payment_method', $method)->where('direction', 'in')->sum('amount'), 2);
            $refunds = round((float) (clone $payments)->where('direction', 'refund')->sum('amount'), 2);
            $opening = round((float) DB::table('sales_cash_sessions')->where('branch_id', $branchId)->whereDate('opened_at', $date)->sum('opening_cash'), 2);
            $cash = $sum('cash');
            $expected = round($opening + $cash - $refunds, 2);
            $now = now();

            DB::table('accounting_daily_closings')->updateOrInsert([
                'branch_id' => $branchId,
                'closing_date' => $date,
            ], [
                'company_id' => $companyId,
                'opening_cash' => $opening,
                'cash_sales' => $cash,
                'card_sales' => $sum('card'),
                'bank_transfer_sales' => $sum('bank_transfer'),
                'mobile_wallet_sales' => $sum('mobile_wallet'),
                'payment_link_sales' => $sum('payment_link'),
                'refunds' => $refunds,
                'expected_cash' => $expected,
                'actual_cash' => round($actualCash, 2),
                'difference' => round($actualCash - $expected, 2),
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => $now,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) DB::table('accounting_daily_closings')->where('branch_id', $branchId)->where('closing_date', $date)->value('id');
            $this->audit('accounting.daily.closed', 'accounting_daily_closing', $id, $branchId, null, ['date' => $date, 'difference' => round($actualCash - $expected, 2)]);

            return $id;
        });
    }

    public function closeMonthly(int $companyId, int $branchId, int $year, int $month, ?string $notes, ?int $userId): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $year, $month, $notes, $userId): int {
            $from = sprintf('%04d-%02d-01', $year, $month);
            $to = date('Y-m-t', strtotime($from));
            $this->assertClosable($branchId, $from, $to);
            if (DB::table('accounting_monthly_closings')->where('company_id', $companyId)->where('branch_id', $branchId)->where('year', $year)->where('month', $month)->exists()) {
                throw new DomainException(__('accounting.errors.period_already_closed'));
            }

            $journals = DB::table('accounting_journals')->where('branch_id', $branchId)->whereBetween('journal_date', [$from, $to])->whereIn('status', ['posted', 'reversed']);
            $now = now();
            $id = DB::table('accounting_monthly_closings')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'year' => $year,
                'month' => $month,
                'total_debit' => round((float) (clone $journals)->sum('total_debit'), 2),
                'total_credit' => round((float) (clone $journals)->sum('total_credit'), 2),
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => $now,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->audit('accounting.month.closed', 'accounting_monthly_closing', $id, $branchId, null, ['year' => $year, 'month' => $month]);

            return $id;
        });
    }

    private function persist(array $header, array $lines, string $status): int
    {
        [$lines, $debit, $credit] = $this->normalizeLines($lines);
        $now = now();
        $journalId = DB::table('accounting_journals')->insertGetId([
            'company_id' => $header['company_id'] ?? null,
            'branch_id' => $header['branch_id'] ?? null,
            'journal_number' => $header['journal_number'] ?? $this->nextNumber(),
            'journal_date' => $header['journal_date'],
            'source_type' => $header['source_type'] ?? 'manual_voucher',
            'source_id' => $header['source_id'] ?? null,
            'reversal_of_id' => $header['reversal_of_id'] ?? null,
            'status' => $status,
            'description' => $header['description'] ?? null,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'posted_at' => $status === 'posted' ? $now : null,
            'created_by' => $header['created_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($lines as $line) {
            DB::table('accounting_journal_lines')->insert([
                'accounting_journal_id' => $journalId,
                'accounting_account_id' => $line['account_id'],
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'party_type' => $line['party_type'],
                'party_id' => $line['party_id'],
                'source_type' => $header['source_type'] ?? 'manual_voucher',
                'source_id' => $header['source_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->audit('accounting.journal.'.($status === 'posted' ? 'posted' : 'drafted'), 'accounting_journal', $journalId, $header['branch_id'] ?? null, null, ['status' => $status, 'total' => $debit]);

        return $journalId;
    }

    private function normalizeLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new DomainException(__('accounting.errors.minimum_lines'));
        }

        $normalized = collect($lines)->map(function (array $line): array {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw new DomainException(__('accounting.errors.invalid_line'));
            }

            return [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'party_type' => $line['party_type'] ?? null,
                'party_id' => $line['party_id'] ?? null,
            ];
        });

        $accountIds = $normalized->pluck('account_id')->unique();
        if ($accountIds->contains(0) || DB::table('accounting_accounts')->whereIn('id', $accountIds)->where('is_active', true)->count() !== $accountIds->count()) {
            throw new DomainException(__('accounting.errors.invalid_account'));
        }

        $debit = round((float) $normalized->sum('debit'), 2);
        $credit = round((float) $normalized->sum('credit'), 2);
        if ((int) round($debit * 100) !== (int) round($credit * 100) || $debit <= 0) {
            throw new DomainException(__('accounting.errors.unbalanced'));
        }

        return [$normalized, $debit, $credit];
    }

    private function assertPeriodOpen(mixed $branchId, string $date): void
    {
        if ($branchId && DB::table('accounting_daily_closings')->where('branch_id', $branchId)->where('closing_date', $date)->where('status', 'closed')->exists()) {
            throw new DomainException(__('accounting.errors.period_closed'));
        }
        if ($branchId && Schema::hasTable('accounting_monthly_closings')) {
            [$year, $month] = array_map('intval', explode('-', substr($date, 0, 7)));
            if (DB::table('accounting_monthly_closings')->where('branch_id', $branchId)->where('year', $year)->where('month', $month)->where('status', 'closed')->exists()) {
                throw new DomainException(__('accounting.errors.period_closed'));
            }
        }
    }

    private function assertClosable(int $branchId, string $from, string $to): void
    {
        if (DB::table('accounting_journals')->where('branch_id', $branchId)->whereBetween('journal_date', [$from, $to])->where('status', 'draft')->exists()) {
            throw new DomainException(__('accounting.errors.drafts_in_period'));
        }
        if (DB::table('accounting_journals')->where('branch_id', $branchId)->whereBetween('journal_date', [$from, $to])->whereRaw('ABS(total_debit - total_credit) > 0.009')->exists()) {
            throw new DomainException(__('accounting.errors.unbalanced_period'));
        }
    }

    private function nextNumber(): string
    {
        return 'JRN-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    private function audit(string $action, string $type, int $id, mixed $branchId, ?array $before, ?array $after): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'branch_id' => $branchId ?: null,
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'before_values' => $before ? json_encode($before) : null,
            'after_values' => $after ? json_encode($after) : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
