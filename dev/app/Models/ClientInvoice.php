<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientInvoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }
}
