<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\WorkspaceNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $r): array
    {
        $u = $r->user();
        if ($u) {
            Inertia::encryptHistory();
        }

        return [...parent::share($r), 'auth' => ['user' => $u?->only('id', 'name', 'email', 'role')],
            'projects' => fn () => ! $u ? [] : ($u->role === 'admin' ? Project::query() : $u->projects())->orderBy('event_date')->get(['projects.id', 'name', 'couple_name', 'status']),
            'unread' => fn () => $u ? WorkspaceNotification::where('user_id', $u->id)->whereNull('read_at')->whereIn('project_id', $u->role === 'admin' ? Project::select('id') : $u->projects()->select('projects.id'))->count() : 0,
            'flash' => ['success' => fn () => $r->session()->get('success')]];
    }
}
