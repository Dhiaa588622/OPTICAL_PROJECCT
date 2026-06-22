<?php

namespace App\Http\Controllers;

use App\Support\AccountingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function storeVoucher(Request $request, AccountingService $accounting): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'journal_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'post_now' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->authorizeBranch($request, (int) $validated['branch_id']);
        if ($request->header('X-Offline-Draft') === '1') {
            return back()->withErrors(['voucher' => __('accounting.errors.offline_posting')]);
        }

        $companyId = (int) DB::table('branches')->where('id', $validated['branch_id'])->value('company_id');
        $header = [
            'company_id' => $companyId,
            'branch_id' => (int) $validated['branch_id'],
            'journal_date' => $validated['journal_date'],
            'source_type' => 'manual_voucher',
            'description' => $validated['description'],
            'created_by' => $request->user()->id,
        ];

        try {
            $request->boolean('post_now')
                ? $accounting->createPosted($header, $validated['lines'])
                : $accounting->createDraft($header, $validated['lines']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['voucher' => $exception->getMessage()]);
        }

        return back()->with('status', $request->boolean('post_now') ? __('accounting.messages.posted') : __('accounting.messages.draft_saved'));
    }

    public function postVoucher(Request $request, int $journal, AccountingService $accounting): RedirectResponse
    {
        try {
            $accounting->postDraft($journal, $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['voucher' => $exception->getMessage()]);
        }

        return back()->with('status', __('accounting.messages.posted'));
    }

    public function updateVoucher(Request $request, int $journal, AccountingService $accounting): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'journal_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->authorizeBranch($request, (int) $validated['branch_id']);
        $companyId = (int) DB::table('branches')->where('id', $validated['branch_id'])->value('company_id');

        try {
            $accounting->updateDraft($journal, [
                'company_id' => $companyId,
                'branch_id' => (int) $validated['branch_id'],
                'journal_date' => $validated['journal_date'],
                'description' => $validated['description'],
            ], $validated['lines'], $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['voucher' => $exception->getMessage()]);
        }

        return back()->with('status', __('accounting.messages.draft_updated'));
    }

    public function reverseVoucher(Request $request, int $journal, AccountingService $accounting): RedirectResponse
    {
        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $accounting->reverse($journal, $validated['journal_date'], $validated['description'], $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['voucher' => $exception->getMessage()]);
        }

        return back()->with('status', __('accounting.messages.reversed'));
    }

    public function closeDaily(Request $request, AccountingService $accounting): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->authorizeBranch($request, (int) $validated['branch_id']);
        $companyId = (int) DB::table('branches')->where('id', $validated['branch_id'])->value('company_id');

        try {
            $accounting->closeDaily($companyId, (int) $validated['branch_id'], $validated['closing_date'], (float) $validated['actual_cash'], $validated['notes'] ?? null, $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['closing' => $exception->getMessage()]);
        }

        return back()->with('status', __('accounting.messages.daily_closed'));
    }

    public function closeMonthly(Request $request, AccountingService $accounting): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'period' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->authorizeBranch($request, (int) $validated['branch_id']);
        [$year, $month] = array_map('intval', explode('-', $validated['period']));
        $companyId = (int) DB::table('branches')->where('id', $validated['branch_id'])->value('company_id');

        try {
            $accounting->closeMonthly($companyId, (int) $validated['branch_id'], $year, $month, $validated['notes'] ?? null, $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['closing' => $exception->getMessage()]);
        }

        return back()->with('status', __('accounting.messages.monthly_closed'));
    }

    private function authorizeBranch(Request $request, int $branchId): void
    {
        abort_unless($request->user()->hasRole('erp-admin') || $request->user()->branches()->where('branches.id', $branchId)->exists(), 403);
    }
}
