<?php

namespace App\Services;

use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorObligation;
use App\Models\VendorPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentAction
{
    public static function balance($invoice): int
    {
        return (int) $invoice->amount - (int) $invoice->payments()->where('status', 'terverifikasi')->sum('amount');
    }

    public static function summary($invoice, string $timezone = 'Asia/Makassar'): array
    {
        $paid = (int) $invoice->payments->where('status', 'terverifikasi')->sum(fn ($p) => (int) $p->amount);
        $balance = (int) $invoice->amount - $paid;

        return ['paid' => $paid, 'balance' => $balance, 'payment_status' => $balance === 0 ? 'lunas' : ($invoice->due_date->toDateString() < now($timezone)->toDateString() ? 'terlambat' : ($paid > 0 ? 'sebagian' : 'belum_dibayar'))];
    }

    public function submit(Project $p, User $u, int $id, array $d, UploadedFile $file, bool $vendor = false): void
    {
        $path = $file->store('proofs/'.$p->id, 'local');
        try {
            DB::transaction(function () use ($p, $u, $id, $d, $file, $vendor, $path) {
                $invoice = ($vendor ? VendorObligation::class : ClientInvoice::class)::where('project_id', $p->id)->lockForUpdate()->findOrFail($id);
                if ((int) $d['amount'] > self::balance($invoice)) {
                    throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi saldo tersisa.']);
                }
                $payment = $invoice->payments()->create(['amount' => $d['amount'], 'paid_on' => $d['paid_on'], 'submitted_by' => $u->id, 'proof_path' => $path, 'proof_name' => basename($file->getClientOriginalName()), 'status' => 'menunggu']);
                Audit::record($u, $p, 'Bukti pembayaran diunggah', $payment, ['amount' => $payment->amount], $vendor ? 'internal' : 'client');
                Audit::notify($p, 'Bukti pembayaran menunggu verifikasi', '/projects/'.$p->id.'/'.($vendor ? 'vendor-payments' : 'payments'), 'payment:'.($vendor ? 'v' : 'c').':'.$payment->id);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function decide(Project $p, User $u, int $id, bool $vendor, bool $cancel = false, ?string $reason = null): void
    {
        $class = $vendor ? VendorPayment::class : ClientPayment::class;
        $reference = $class::findOrFail($id);
        DB::transaction(function () use ($p, $u, $id, $vendor, $cancel, $reason, $class, $reference) {
            $invoice = ($vendor ? VendorObligation::class : ClientInvoice::class)::where('project_id', $p->id)->lockForUpdate()->findOrFail($vendor ? $reference->vendor_obligation_id : $reference->client_invoice_id);
            $payment = $class::lockForUpdate()->findOrFail($id);
            if ($payment->status === 'dibatalkan' || (! $cancel && $payment->status !== 'menunggu')) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }
            // Locking read memakai pembayaran terkini, termasuk jika pemanggil
            // sudah memiliki snapshot transaksi REPEATABLE READ sebelumnya.
            $paid = $invoice->payments()->where('status', 'terverifikasi')->lockForUpdate()->get(['amount'])->reduce(fn (int $total, $row): int => $total + (int) $row->amount, 0);
            if (! $cancel && (int) $payment->amount > (int) $invoice->amount - $paid) {
                throw ValidationException::withMessages(['payment' => 'Nilai bukti melebihi saldo saat ini. Batalkan bukti dan unggah koreksi.']);
            }
            $payment->update($cancel ? ['status' => 'dibatalkan', 'cancellation_reason' => $reason] : ['status' => 'terverifikasi', 'verified_by' => $u->id, 'verified_at' => now()]);
            Audit::record($u, $p, $cancel ? 'Pembayaran dibatalkan' : 'Pembayaran diverifikasi', $payment, ['amount' => $payment->amount, 'reason' => $reason], $vendor ? 'internal' : 'client');
            Audit::notify($p, $cancel ? 'Pembayaran dibatalkan' : 'Pembayaran telah diverifikasi', '/projects/'.$p->id.'/'.($vendor ? 'vendor-payments' : 'payments'), 'payment-result:'.($vendor ? 'v' : 'c').':'.$payment->id.':'.$payment->status, ! $vendor);
        });
    }
}
