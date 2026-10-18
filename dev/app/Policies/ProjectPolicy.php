<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $u, Project $p): bool
    {
        return $u->active && ($u->role === 'admin' || $p->members()->where('users.id', $u->id)->exists());
    }

    public function manage(User $u, Project $p): bool
    {
        return $p->status !== 'arsip' && $this->view($u, $p) && in_array($u->role, ['admin', 'pic'], true);
    }

    public function internal(User $u, Project $p): bool
    {
        return $this->view($u, $p) && $u->role !== 'client';
    }
}
