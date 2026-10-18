<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalDecision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalAction
{
    public function run(Project $p, User $u, int $id, string $op, array $d): Approval
    {
        return DB::transaction(function () use ($p, $u, $id, $op, $d) {
            $a = Approval::where('project_id', $p->id)->lockForUpdate()->findOrFail($id);
            $fail = fn ($m) => throw ValidationException::withMessages(['approval' => $m]);
            if ($u->role === 'client' && ! in_array($a->status, ['diajukan', 'disetujui', 'perlu_revisi', 'ditolak'], true)) {
                abort(403);
            }
            if ($op === 'submit') {
                if ($a->status !== 'draft') {
                    $fail('Hanya draft yang dapat diajukan.');
                }
                $clients = $p->members()->where('role', 'client')->where('active', true)->pluck('users.id');
                if ($clients->count() < ($a->rule === 'keduanya' ? 2 : 1)) {
                    $fail('Anggota pasangan belum lengkap.');
                }
                $a->recipients()->sync($clients);
                $a->update(['status' => 'diajukan', 'submitted_at' => now()]);
            } elseif ($op === 'decide') {
                abort_unless($u->role === 'client' && $a->recipients()->where('users.id', $u->id)->exists(), 403);
                if ($a->status !== 'diajukan' || $a->decisions()->where('user_id', $u->id)->exists()) {
                    $fail('Versi ini sudah diputuskan atau tidak lagi menunggu keputusan.');
                }
                ApprovalDecision::create(['approval_id' => $a->id, 'user_id' => $u->id, ...$d]);
                if ($d['decision'] !== 'disetujui') {
                    $a->status = $d['decision'];
                } elseif ($a->rule === 'salah_satu' || $a->decisions()->where('decision', 'disetujui')->count() >= $a->recipients()->count()) {
                    $a->status = 'disetujui';
                }
                $a->save();
            } elseif ($op === 'comment') {
                ApprovalComment::create(['approval_id' => $a->id, 'user_id' => $u->id, 'body' => $d['body']]);
            } elseif ($op === 'cancel-approval') {
                if (! in_array($a->status, ['draft', 'diajukan'], true)) {
                    $fail('Keputusan final tidak dapat dibatalkan. Buat revisi baru.');
                }
                $a->update(['status' => 'dibatalkan']);
            } elseif ($op === 'revise') {
                if ($a->status === 'draft') {
                    $fail('Simpan perubahan draft sebelum membuat versi baru.');
                }
                if (Approval::where('previous_id', $a->id)->exists()) {
                    $fail('Revisi versi ini sudah tersedia.');
                }
                if ($a->status === 'diajukan') {
                    $a->update(['status' => 'dibatalkan']);
                }
                $a = Approval::create(['project_id' => $p->id, 'title' => $a->title, 'material' => $a->material, 'version' => $a->version + 1, 'previous_id' => $a->id, 'rule' => $a->rule, 'status' => 'draft', 'due_date' => $a->due_date, 'created_by' => $u->id]);
            }
            Audit::record($u, $p, match ($op) {
                'submit' => 'Persetujuan diajukan', 'decide' => 'Keputusan pasangan dicatat', 'comment' => 'Komentar persetujuan ditambahkan', 'cancel-approval' => 'Pengajuan dibatalkan', 'revise' => 'Versi materi baru dibuat'
            }, $a, ['status' => $a->status, 'version' => $a->version], 'client');
            Audit::notify($p, 'Persetujuan: '.$a->title, '/projects/'.$p->id.'/approvals', 'approval:'.$op.':'.$a->id.':'.$u->id, $op === 'submit');

            return $a;
        });
    }
}
