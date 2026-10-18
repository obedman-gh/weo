<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\ClientInvoice;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\Task;
use App\Models\User;
use App\Models\VendorObligation;

class ProjectSummary
{
    public function build(Project $p, User $u, ?string $from = null, ?string $to = null): array
    {
        $client = $u->role === 'client';
        $tasks = Task::where('project_id', $p->id)->when($client, fn ($q) => $q->where('visibility', 'client'))->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))->get();
        $approvals = Approval::where('project_id', $p->id)->when($client, fn ($q) => $q->whereNotIn('status', ['draft', 'dibatalkan']))->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))->get();
        $invoices = ClientInvoice::where('project_id', $p->id)->with('payments')->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))->get()->map(fn ($i) => [...$i->toArray(), ...PaymentAction::summary($i, $p->timezone)]);
        $today = now($p->timezone)->toDateString();

        return ['progress' => $p->progress($u), 'tasks' => ['total' => $tasks->count(), 'completed' => $tasks->where('status', 'selesai')->count(), 'blocked' => $tasks->where('status', 'terhambat')->count(), 'overdue' => $tasks->filter(fn ($t) => $t->status !== 'selesai' && $t->due_date->toDateString() < $today)->count()],
            'approval_counts' => $approvals->countBy('status'), 'pending_approvals' => $approvals->where('status', 'diajukan')->count(), 'invoices' => $invoices, 'billed' => $invoices->sum(fn ($i) => (int) $i['amount']), 'paid' => $invoices->sum('paid'), 'balance' => $invoices->sum('balance'),
            'upcoming' => $tasks->where('status', '!=', 'selesai')->sortBy('due_date')->take(6)->values(),
            'vendors' => in_array($u->role, ['admin', 'pic'], true) ? ProjectVendor::where('project_id', $p->id)->with('vendor')->get() : [],
            'vendor_finance' => in_array($u->role, ['admin', 'pic'], true) ? VendorObligation::where('project_id', $p->id)->with('payments')->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))->get()->map(fn ($i) => [...$i->toArray(), ...PaymentAction::summary($i, $p->timezone)]) : [],
            'activities' => ActivityLog::where('project_id', $p->id)->when($client, fn ($q) => $q->where('visibility', 'client'))->with('user:id,name')->latest()->limit(8)->get()];
    }
}
