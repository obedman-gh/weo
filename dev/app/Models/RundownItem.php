<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RundownItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration' => 'integer'];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function projectVendor()
    {
        return $this->belongsTo(ProjectVendor::class);
    }

    public function rundown()
    {
        return $this->belongsTo(Rundown::class);
    }
}
