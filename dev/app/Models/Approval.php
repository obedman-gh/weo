<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'due_date' => 'date'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function decisions()
    {
        return $this->hasMany(ApprovalDecision::class)->with('user:id,name');
    }

    public function comments()
    {
        return $this->hasMany(ApprovalComment::class)->with('user:id,name');
    }

    public function recipients()
    {
        return $this->belongsToMany(User::class, 'approval_recipients');
    }
}
