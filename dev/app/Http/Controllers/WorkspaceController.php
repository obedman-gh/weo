<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceRequest;
use App\Models\Approval;
use App\Models\ChecklistTemplate;
use App\Models\ClientInvoice;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\Rundown;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\VendorObligation;
use App\Models\WorkspaceNotification;
use App\Services\DashboardSummary;
use App\Services\PaymentAction;
use App\Services\ProjectSummary;
use App\Services\RundownAction;
use App\Services\WorkspaceAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function dashboard(Request $r, ProjectSummary $summary)
    {
        $q = $r->user()->role === 'admin' ? Project::query() : $r->user()->projects();
        if ($r->user()->role === 'client') {
            $p = $q->orderBy('event_date')->first();
            if ($p) {
                return redirect('/projects/'.$p->id.'/overview');
            }
        }
        $projects = $q->with('members:id,name,role')->where('status', '!=', 'arsip')->orderBy('event_date')->get();

        return Inertia::render('Dashboard', ['cards' => app(DashboardSummary::class)->cards($projects), 'today' => now('Asia/Makassar')->toDateString()]);
    }

    public function show(Request $r, Project $project, string $module, ProjectSummary $summary)
    {
        Gate::authorize('view', $project);
        $u = $r->user();
        $client = $u->role === 'client';
        $manage = Gate::allows('manage', $project);
        abort_unless(in_array($module, ['overview', 'progress', 'tasks', 'approvals', 'next-actions', 'vendors', 'payments', 'vendor-payments', 'documents', 'meetings', 'rundowns', 'reports']), 404);
        if ($module === 'vendor-payments') {
            abort_unless(in_array($u->role, ['admin', 'pic']), 403);
        }
        $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:40', 'priority' => 'nullable|in:rendah,normal,tinggi', 'assignee_id' => 'nullable|integer', 'due' => 'nullable|in:overdue,today,upcoming,mine', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($r->filled('from') ? ['after_or_equal:from'] : [])], 'task_id' => 'nullable|integer', 'meeting_id' => 'nullable|integer', 'project_vendor_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
        $props = ['project' => $project->load('members:id,name,role'), 'module' => $module, 'manage' => $manage, 'filters' => $r->only('q', 'status', 'priority', 'assignee_id', 'due', 'from', 'to'), 'summary' => $summary->build($project, $u, $r->from, $r->to), 'records' => null, 'options' => ['members' => $project->members()->get(['users.id', 'name', 'role'])]];
        $class = match ($module) {
            'progress','tasks','next-actions' => Task::class,'approvals' => Approval::class,'vendors' => ProjectVendor::class,'payments' => ClientInvoice::class,'vendor-payments' => VendorObligation::class,'documents' => Document::class,'meetings' => Meeting::class,'rundowns' => Rundown::class,default => null
        };
        if ($class) {
            $q = $class::where('project_id', $project->id);
            if ($client && in_array($module, ['progress', 'tasks', 'next-actions', 'documents', 'meetings', 'rundowns'])) {
                $q->where('visibility', 'client');
            }
            if ($client && $module === 'approvals') {
                $q->whereNotIn('status', ['draft', 'dibatalkan']);
            }
            if ($module === 'progress') {
                $q->where('kind', 'checklist');
            }
            if ($module === 'tasks') {
                $q->where('kind', 'task');
            }
            if (in_array($module, ['progress', 'tasks', 'next-actions'])) {
                $q->with('assignee:id,name')->when($r->status, fn ($q) => $q->where('status', $r->status))->when($r->priority, fn ($q) => $q->where('priority', $r->priority))->when($r->assignee_id, fn ($q) => $q->where('assignee_id', $r->assignee_id));
                $today = now($project->timezone)->toDateString();
                if ($module === 'next-actions') {
                    $q->where('status', '!=', 'selesai');
                }
                if ($r->due === 'mine') {
                    $q->where('assignee_id', $u->id);
                }
                if ($r->due === 'overdue') {
                    $q->where('due_date', '<', $today)->where('status', '!=', 'selesai');
                }
                if ($r->due === 'today') {
                    $q->where('due_date', $today);
                }
                if ($r->due === 'upcoming') {
                    $q->where('due_date', '>', $today);
                }
                if ($r->from) {
                    $q->where('due_date', '>=', $r->from);
                } if ($r->to) {
                    $q->where('due_date', '<=', $r->to);
                }
                $q->orderBy('due_date');
            }
            if ($module === 'documents') {
                $q->with('versions');
                foreach (['task_id', 'meeting_id', 'project_vendor_id'] as $relation) {
                    if ($r->filled($relation)) {
                        $q->where($relation, $r->integer($relation));
                    }
                }
            }
            if ($module === 'approvals') {
                $q->with('decisions', 'comments', 'recipients:id,name')->when($r->status, fn ($q) => $q->where('status', $r->status));
            }
            if ($module === 'vendors') {
                $q->with('vendor');
            }
            if (in_array($module, ['payments', 'vendor-payments'])) {
                $q->with('payments');
            }
            if ($module === 'rundowns') {
                $q->with('items.assignee:id,name', 'items.projectVendor:id,vendor_id', 'items.projectVendor.vendor:id,name');
            }
            if ($r->q && $module !== 'vendors') {
                $q->where('title', 'like', '%'.$r->q.'%');
            }
            if ($r->q && $module === 'vendors') {
                $q->whereHas('vendor', fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'));
            }
            $props['records'] = $q->orderByDesc('id')->paginate(12)->withQueryString()->through(function ($record) use ($module, $client, $project) {
                $a = $record->toArray();
                if (in_array($module, ['payments', 'vendor-payments'])) {
                    $a = [...$a, ...PaymentAction::summary($record, $project->timezone)];
                }
                if ($module === 'rundowns') {
                    $a['conflicts'] = RundownAction::conflicts($record);
                }
                if ($module === 'vendors' && $client) {
                    unset($a['agreed_price'],$a['internal_notes']);
                    $a['vendor'] = collect($a['vendor'])->only(['id', 'name', 'category', 'services'])->all();
                }

                return $a;
            });
        }
        if ($manage) {
            $props['options'] += ['vendors' => Vendor::orderBy('name')->get(), 'project_vendors' => ProjectVendor::where('project_id', $project->id)->with('vendor')->get(), 'meetings' => Meeting::where('project_id', $project->id)->get(['id', 'title']), 'tasks' => Task::where('project_id', $project->id)->get(['id', 'title']), 'templates' => ChecklistTemplate::all(), 'documents' => Document::where('project_id', $project->id)->where('visibility', 'client')->with('versions')->get(), 'rundowns' => Rundown::where('project_id', $project->id)->where('status', 'final')->where('visibility', 'client')->get()];
        }
        if ($module === 'next-actions') {
            $approvals = Approval::where('project_id', $project->id)->where('status', 'diajukan');
            $today = now($project->timezone)->toDateString();
            if ($r->q) {
                $approvals->where('title', 'like', '%'.$r->q.'%');
            }
            if ($r->from) {
                $approvals->whereDate('due_date', '>=', $r->from);
            }
            if ($r->to) {
                $approvals->whereDate('due_date', '<=', $r->to);
            }
            if ($r->due === 'overdue') {
                $approvals->whereDate('due_date', '<', $today);
            }
            if ($r->due === 'today') {
                $approvals->whereDate('due_date', $today);
            }
            if ($r->due === 'upcoming') {
                $approvals->whereDate('due_date', '>', $today);
            }
            if ($r->due === 'mine' || ($client && $r->assignee_id)) {
                $approvals->whereHas('recipients', fn ($q) => $q->where('users.id', $u->id));
            } elseif ($r->assignee_id) {
                $approvals->whereHas('recipients', fn ($q) => $q->where('users.id', $r->integer('assignee_id')));
            }
            $props['pendingApprovals'] = $approvals->orderBy('due_date')->get(['id', 'title', 'due_date']);
            $props['outstandingInvoices'] = collect($props['summary']['invoices'])->where('balance', '>', 0)->filter(function ($i) use ($r, $today, $client) {
                $due = substr($i['due_date'], 0, 10);
                if ($r->q && ! str_contains(mb_strtolower($i['title']), mb_strtolower($r->q))) {
                    return false;
                }
                if ($r->due === 'overdue') {
                    return $due < $today;
                }
                if ($r->due === 'today') {
                    return $due === $today;
                }
                if ($r->due === 'upcoming') {
                    return $due > $today;
                }
                if ($r->due === 'mine' && ! $client) {
                    return false;
                }

                return true;
            })->values();
        }

        return Inertia::render('Workspace', $props);
    }

    public function mutate(WorkspaceRequest $r, Project $project, string $operation, ?int $id = null)
    {
        app(WorkspaceAction::class)->run($r, $project, $operation, $id);

        return back()->with('success', 'Perubahan berhasil disimpan.');
    }

    public function notifications(Request $r)
    {
        $allowed = $r->user()->role === 'admin' ? Project::select('id') : $r->user()->projects()->select('projects.id');

        return Inertia::render('Notifications', ['records' => WorkspaceNotification::where('user_id', $r->user()->id)->whereIn('project_id', $allowed)->latest()->paginate(20)]);
    }

    public function readNotification(Request $r, WorkspaceNotification $notification)
    {
        abort_unless($notification->user_id === $r->user()->id, 403);
        Gate::authorize('view', $notification->project);
        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai dibaca.');
    }
}
