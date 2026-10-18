<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Task;
use Illuminate\Support\Collection;

class DashboardSummary
{
    public function cards(Collection $projects): Collection
    {
        $ids = $projects->pluck('id');
        $tasks = Task::whereIn('project_id', $ids)->selectRaw("project_id, SUM(CASE WHEN kind='checklist' AND required=1 AND applicable=1 THEN 1 ELSE 0 END) required_total, SUM(CASE WHEN kind='checklist' AND required=1 AND applicable=1 AND status='selesai' THEN 1 ELSE 0 END) required_done")->groupBy('project_id')->get()->keyBy('project_id');
        $pending = Approval::whereIn('project_id', $ids)->where('status', 'diajukan')->selectRaw('project_id,COUNT(*) total')->groupBy('project_id')->pluck('total', 'project_id');
        $overdue = Task::whereIn('project_id', $ids)->where('status', '!=', 'selesai')->get(['project_id', 'due_date'])->groupBy('project_id');

        return $projects->map(function ($p) use ($tasks, $pending, $overdue) {
            $t = $tasks->get($p->id);
            $today = now($p->timezone)->toDateString();

            return ['project' => $p, 'summary' => ['progress' => $t && $t->required_total ? (int) round($t->required_done * 100 / $t->required_total) : null, 'tasks' => ['overdue' => ($overdue->get($p->id) ?? collect())->filter(fn ($t) => $t->due_date->toDateString() < $today)->count()], 'pending_approvals' => (int) ($pending[$p->id] ?? 0)]];
        });
    }
}
