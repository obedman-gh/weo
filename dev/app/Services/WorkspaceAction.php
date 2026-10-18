<?php

namespace App\Services;

use App\Http\Requests\WorkspaceRequest;
use App\Models\Approval;
use App\Models\ChecklistTemplate;
use App\Models\ClientInvoice;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\Rundown;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\VendorObligation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkspaceAction
{
    public function run(WorkspaceRequest $r, Project $p, string $op, ?int $id): void
    {
        $d = $r->validated();
        $u = $r->user();
        if (in_array($op, ['submit', 'decide', 'comment', 'cancel-approval', 'revise', 'payment', 'vendor-payment', 'verify', 'verify-vendor', 'cancel-payment', 'cancel-vendor-payment', 'rundown-item', 'rundown-edit', 'rundown-remove', 'rundown-move', 'finalize', 'revise-rundown', 'document-version', 'task-status'], true)) {
            abort_unless($id, 404);
        }
        if (in_array($op, ['submit', 'decide', 'comment', 'cancel-approval', 'revise'], true)) {
            app(ApprovalAction::class)->run($p, $u, $id, $op, $d);

            return;
        }
        if (in_array($op, ['payment', 'vendor-payment'], true)) {
            app(PaymentAction::class)->submit($p, $u, $id, $d, $r->file('file'), $op === 'vendor-payment');

            return;
        }
        if (in_array($op, ['verify', 'verify-vendor', 'cancel-payment', 'cancel-vendor-payment'], true)) {
            app(PaymentAction::class)->decide($p, $u, $id, in_array($op, ['verify-vendor', 'cancel-vendor-payment']), str_starts_with($op, 'cancel'), $d['reason'] ?? null);

            return;
        }
        if (in_array($op, ['rundown-item', 'rundown-edit', 'rundown-remove', 'rundown-move', 'finalize', 'revise-rundown'], true)) {
            app(RundownAction::class)->run($p, $u, $op, $id, $d);

            return;
        }
        if ($op === 'documents' || $op === 'document-version') {
            $this->document($r, $p, $d, $id);

            return;
        }
        DB::transaction(function () use ($p, $op, $id, $d, $u) {
            $model = null;
            if ($op === 'tasks' || $op === 'task-status') {
                $model = $id ? Task::where('project_id', $p->id)->lockForUpdate()->findOrFail($id) : new Task(['project_id' => $p->id]);
                if ($u->role === 'team') {
                    abort_unless($model->assignee_id === $u->id, 403);
                }
                $model->fill($d)->save();
                if ($model->assignee_id) {
                    Audit::notify($p, 'Tugas: '.$model->title, '/projects/'.$p->id.'/tasks', 'task:'.$model->id.':'.$model->updated_at->format('Uu'), $model->visibility === 'client', $model->assignee_id);
                }
            } elseif ($op === 'meetings') {
                $d['starts_at'] = Carbon::parse($d['starts_at'], $p->timezone)->utc();
                $model = $id ? Meeting::where('project_id', $p->id)->findOrFail($id) : new Meeting(['project_id' => $p->id]);
                $model->fill($d)->save();
            } elseif ($op === 'vendors') {
                $model = $id ? ProjectVendor::where('project_id', $p->id)->findOrFail($id) : ProjectVendor::firstOrNew(['project_id' => $p->id, 'vendor_id' => $d['vendor_id']]);
                $model->fill($d)->save();
            } elseif ($op === 'vendor-master') {
                abort_unless($u->role === 'admin', 403);
                $model = $id ? Vendor::findOrFail($id) : new Vendor;
                $model->fill($d)->save();
            } elseif ($op === 'approvals') {
                $model = $id ? Approval::where('project_id', $p->id)->lockForUpdate()->findOrFail($id) : new Approval(['project_id' => $p->id, 'version' => 1, 'created_by' => $u->id]);
                if ($model->exists && $model->status !== 'draft') {
                    throw ValidationException::withMessages(['approval' => 'Materi yang sudah diajukan harus dibuat sebagai revisi baru.']);
                }
                if (! empty($d['document_version_id'])) {
                    $v = DocumentVersion::with('document')->findOrFail($d['document_version_id']);
                    abort_unless($v->document->project_id === $p->id && $v->document->visibility === 'client', 403);
                }
                $model->fill($d)->save();
            } elseif ($op === 'invoices' || $op === 'obligations') {
                abort_if($id, 422, 'Tagihan yang sudah dibuat tidak dapat ditimpa. Koreksi pembayaran melalui pembatalan beralasan.');
                $model = ($op === 'invoices' ? ClientInvoice::class : VendorObligation::class)::create(['project_id' => $p->id, ...$d]);
            } elseif ($op === 'rundowns') {
                $p->newQuery()->whereKey($p->id)->lockForUpdate()->first();
                $model = Rundown::create(['project_id' => $p->id, ...$d, 'version' => (Rundown::where('project_id', $p->id)->max('version') ?? 0) + 1]);
            } elseif ($op === 'template') {
                abort_unless($u->role === 'admin', 403);
                $model = $id ? ChecklistTemplate::findOrFail($id) : new ChecklistTemplate;
                $model->fill($d)->save();
            } elseif ($op === 'apply-template') {
                $model = ChecklistTemplate::findOrFail($d['template_id']);
                foreach ($model->items as $i => $item) {
                    Task::firstOrCreate(['project_id' => $p->id, 'template_key' => $model->id.':'.$i], [...$item, 'kind' => 'checklist', 'required' => true, 'due_date' => $p->event_date->copy()->subDays(30)->toDateString()]);
                }
            }
            abort_unless($model, 422, 'Tindakan tidak tersedia.');
            Audit::record($u, $p, match ($op) {
                'tasks','task-status' => 'Pekerjaan diperbarui', 'meetings' => 'Catatan meeting disimpan', 'vendors' => 'Penugasan vendor disimpan', 'vendor-master' => 'Master vendor disimpan', 'approvals' => 'Draft persetujuan disimpan', 'invoices' => 'Tagihan klien dibuat', 'obligations' => 'Kewajiban vendor dibuat', 'rundowns' => 'Draft rundown dibuat', 'template' => 'Template disimpan', 'apply-template' => 'Template diterapkan', default => 'Data proyek diperbarui'
            }, $model, [], ($model->visibility ?? 'internal'));
        });
    }

    private function document(WorkspaceRequest $r, Project $p, array $d, ?int $id): void
    {
        $file = $r->file('file');
        $path = $file->store('documents/'.$p->id, 'local');
        unset($d['file']);
        try {
            DB::transaction(function () use ($r, $p, $d, $id, $file, $path) {
                $doc = $id ? Document::where('project_id', $p->id)->lockForUpdate()->findOrFail($id) : Document::create(['project_id' => $p->id, ...$d]);
                $v = $doc->versions()->create(['version' => ($doc->versions()->max('version') ?? 0) + 1, 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $r->user()->id]);
                Audit::record($r->user(), $p, 'Versi dokumen diunggah', $v, ['version' => $v->version], $doc->visibility);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
