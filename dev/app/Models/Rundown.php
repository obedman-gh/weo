<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rundown extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(RundownItem::class)->orderBy('position');
    }
}
