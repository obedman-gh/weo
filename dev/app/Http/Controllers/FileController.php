<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use App\Models\DocumentVersion;
use App\Models\VendorPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function document(Request $r, DocumentVersion $version)
    {
        $doc = $version->document;
        Gate::authorize('view', $doc->project);
        abort_if($r->user()->role === 'client' && $doc->visibility !== 'client', 403);
        abort_unless(Storage::disk('local')->exists($version->path), 404);

        return Storage::disk('local')->download($version->path, $version->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function proof(Request $r, string $type, int $id)
    {
        abort_unless(in_array($type, ['client', 'vendor']), 404);
        $vendor = $type === 'vendor';
        $payment = ($vendor ? VendorPayment::class : ClientPayment::class)::findOrFail($id);
        $p = $vendor ? $payment->obligation->project : $payment->invoice->project;
        Gate::authorize('view', $p);
        if ($vendor) {
            abort_unless(in_array($r->user()->role, ['admin', 'pic']), 403);
        } else {
            abort_unless(in_array($r->user()->role, ['admin', 'pic', 'client']), 403);
        }
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path, $payment->proof_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
