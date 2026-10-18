<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function invoices()
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function couple()
    {
        return $this->hasOne(CoupleProfile::class);
    }

    public function progress(?User $viewer = null): ?int
    {
        $query = $this->tasks()->where('kind', 'checklist')->where('required', true)->where('applicable', true);
        if ($viewer?->role === 'client') {
            $query->where('visibility', 'client');
        }
        $total = (clone $query)->count();

        return $total ? (int) round((clone $query)->where('status', 'selesai')->count() * 100 / $total) : null;
    }
}
