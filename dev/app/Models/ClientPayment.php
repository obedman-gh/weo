<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPayment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function invoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }
}
