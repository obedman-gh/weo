<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function obligation()
    {
        return $this->belongsTo(VendorObligation::class, 'vendor_obligation_id');
    }
}
