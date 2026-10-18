<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(User $u, ?Project $p, string $action, Model $subject, array $changes = [], string $visibility = 'internal'): void
    {
        ActivityLog::create(['user_id' => $u->id, 'project_id' => $p?->id, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'changes' => $changes, 'visibility' => $visibility]);
    }

    public static function notify(Project $p, string $title, string $url, string $key, bool $clients = false, ?int $onlyUser = null): void
    {
        $members = $p->members()->where('active', true)->when(! $clients, fn ($q) => $q->where('role', '!=', 'client'))->when($onlyUser, fn ($q) => $q->where('users.id', $onlyUser))->get();
        foreach ($members as $m) {
            WorkspaceNotification::firstOrCreate(['dedupe_key' => $key.':'.$m->id], ['user_id' => $m->id, 'project_id' => $p->id, 'title' => $title, 'url' => $url]);
        }
    }
}
