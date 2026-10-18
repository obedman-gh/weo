<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
