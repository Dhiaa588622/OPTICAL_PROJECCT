<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    public function trialBalance(?int $branchId = null, ?string $from = null, ?string $to = null): Collection
    {
        return $this->balances($branchId, $from, $to)->map(function (object $account): object {
            $net = round((float) $account->debit - (float) $account->credit, 2);
            $account->debit_balance = $net > 0 ? $net : 0.0;
            $account->credit_balance = $net < 0 ? abs($net) : 0.0;

            return $account;
        });
    }

    public function ledger(int $accountId, ?int $branchId = null, ?string $from = null, ?string $to = null): Collection
    {
        $running = 0.0;

        return DB::table('accounting_journal_lines')
            ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
            ->where('accounting_journal_lines.accounting_account_id', $accountId)
            ->whereIn('accounting_journals.status', ['posted', 'reversed'])
            ->when($branchId, fn ($query) => $query->where('accounting_journals.branch_id', $branchId))
            ->when($from, fn ($query) => $query->whereDate('accounting_journals.journal_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('accounting_journals.journal_date', '<=', $to))
            ->select('accounting_journals.journal_number', 'accounting_journals.journal_date', 'accounting_journals.description as journal_description', 'accounting_journal_lines.description', 'accounting_journal_lines.debit', 'accounting_journal_lines.credit')
            ->orderBy('accounting_journals.journal_date')
            ->orderBy('accounting_journals.id')
            ->orderBy('accounting_journal_lines.id')
            ->get()
            ->map(function (object $line) use (&$running): object {
                $running = round($running + (float) $line->debit - (float) $line->credit, 2);
                $line->running_balance = $running;

                return $line;
            });
    }

    public function profitAndLoss(?int $branchId = null, ?string $from = null, ?string $to = null): Collection
    {
        $rows = $this->balances($branchId, $from, $to)
            ->whereIn('type', ['revenue', 'expense'])
            ->map(function (object $account): object {
                $account->amount = $account->type === 'revenue'
                    ? round((float) $account->credit - (float) $account->debit, 2)
                    : round((float) $account->debit - (float) $account->credit, 2);

                return $account;
            })->values();
        $revenue = round((float) $rows->where('type', 'revenue')->sum('amount'), 2);
        $expenses = round((float) $rows->where('type', 'expense')->sum('amount'), 2);
        $rows->push((object) ['code' => 'TOTAL', 'name' => 'Net profit / (loss)', 'type' => 'net_income', 'amount' => round($revenue - $expenses, 2)]);

        return $rows;
    }

    public function balanceSheet(?int $branchId = null, ?string $to = null): Collection
    {
        $balances = $this->balances($branchId, null, $to);
        $rows = $balances->whereIn('type', ['asset', 'liability', 'equity'])->map(function (object $account): object {
            $account->amount = $account->type === 'asset'
                ? round((float) $account->debit - (float) $account->credit, 2)
                : round((float) $account->credit - (float) $account->debit, 2);

            return $account;
        })->values();
        $income = $balances->where('type', 'revenue')->sum(fn ($row) => (float) $row->credit - (float) $row->debit);
        $expenses = $balances->where('type', 'expense')->sum(fn ($row) => (float) $row->debit - (float) $row->credit);
        $rows->push((object) ['code' => 'RETAINED', 'name' => 'Current earnings', 'type' => 'equity', 'amount' => round($income - $expenses, 2)]);

        return $rows;
    }

    private function balances(?int $branchId, ?string $from, ?string $to): Collection
    {
        return DB::table('accounting_accounts')
            ->leftJoin('accounting_journal_lines', 'accounting_journal_lines.accounting_account_id', '=', 'accounting_accounts.id')
            ->leftJoin('accounting_journals', function ($join) use ($branchId, $from, $to): void {
                $join->on('accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
                    ->whereIn('accounting_journals.status', ['posted', 'reversed']);
                if ($branchId) {
                    $join->where('accounting_journals.branch_id', '=', $branchId);
                }
                if ($from) {
                    $join->where('accounting_journals.journal_date', '>=', $from);
                }
                if ($to) {
                    $join->where('accounting_journals.journal_date', '<=', $to);
                }
            })
            ->where('accounting_accounts.is_active', true)
            ->selectRaw('accounting_accounts.id, accounting_accounts.code, accounting_accounts.name, accounting_accounts.type, accounting_accounts.normal_balance, coalesce(sum(case when accounting_journals.id is not null then accounting_journal_lines.debit else 0 end), 0) as debit, coalesce(sum(case when accounting_journals.id is not null then accounting_journal_lines.credit else 0 end), 0) as credit')
            ->groupBy('accounting_accounts.id', 'accounting_accounts.code', 'accounting_accounts.name', 'accounting_accounts.type', 'accounting_accounts.normal_balance')
            ->orderBy('accounting_accounts.code')
            ->get();
    }
}
