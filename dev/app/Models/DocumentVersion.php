<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
