<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoupleProfile extends Model
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
}
