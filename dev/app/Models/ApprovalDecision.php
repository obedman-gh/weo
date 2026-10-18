<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDecision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
