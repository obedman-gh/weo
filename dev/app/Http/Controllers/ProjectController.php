<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\ChecklistTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $q = $r->user()->role === 'admin' ? Project::query() : $r->user()->projects();

        return Inertia::render('Projects', ['records' => $q->with('members:id,name,role', 'couple')->when($r->q, fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'))->orderBy('event_date')->paginate(12)->withQueryString(), 'users' => $r->user()->role === 'admin' ? User::where('active', true)->get(['id', 'name', 'role']) : [], 'templates' => $r->user()->role === 'admin' ? ChecklistTemplate::all() : [], 'filters' => $r->only('q')]);
    }

    public function store(ProjectRequest $r)
    {
        return $this->save($r);
    }

    public function update(ProjectRequest $r, Project $project)
    {
        Gate::authorize('view', $project);

        return $this->save($r, $project);
    }

    private function save(ProjectRequest $r, ?Project $project = null)
    {
        DB::transaction(function () use ($r, $project) {
            $d = $r->validated();
            $new = ! $project;
            $project ??= new Project;
            $project->fill(collect($d)->only(['name', 'couple_name', 'event_date', 'venue', 'timezone', 'status'])->all())->save();
            $project->members()->sync($d['member_ids']);
            $project->couple()->updateOrCreate([], collect($d)->only(['bride_name', 'groom_name'])->all());
            if ($new && ! empty($d['template_id'])) {
                foreach (ChecklistTemplate::findOrFail($d['template_id'])->items as $i => $item) {
                    Task::create([...$item, 'project_id' => $project->id, 'kind' => 'checklist', 'required' => true, 'due_date' => $project->event_date->copy()->subDays(30)->toDateString(), 'template_key' => $d['template_id'].':'.$i]);
                }
            }
            Audit::record($r->user(), $project, $new ? 'Proyek dibuat' : 'Proyek diperbarui', $project, ['status' => $project->status]);
        });

        return back()->with('success', 'Proyek dan anggota berhasil disimpan.');
    }
}
