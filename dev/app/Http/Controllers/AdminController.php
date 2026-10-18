<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return Inertia::render('Admin', ['users' => User::orderBy('name')->paginate(20), 'logs' => ActivityLog::with('user:id,name')->latest()->paginate(20, ['*'], 'log_page')]);
    }

    public function store(UserRequest $r)
    {
        $u = User::create($r->validated());
        Audit::record($r->user(), null, 'Akun dibuat', $u, ['role' => $u->role]);

        return back()->with('success', 'Akun berhasil dibuat.');
    }

    public function update(UserRequest $r, User $user)
    {
        abort_if($user->id === $r->user()->id && (! $r->boolean('active') || $r->role !== 'admin'), 422, 'Admin tidak dapat mencabut aksesnya sendiri.');
        $d = $r->validated();
        if (empty($d['password'])) {
            unset($d['password']);
        } $user->update($d);
        Audit::record($r->user(), null, 'Akun diperbarui', $user, ['role' => $user->role, 'active' => $user->active]);

        return back()->with('success', 'Akun diperbarui.');
    }
}
