<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVendor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['agreed_price' => 'decimal:2'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
