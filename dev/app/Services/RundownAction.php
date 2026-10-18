<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Rundown;
use App\Models\RundownItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RundownAction
{
    public static function conflicts(Rundown $r): array
    {
        $items = $r->items;
        $out = [];
        foreach ($items as $i => $a) {
            foreach ($items->slice($i + 1) as $b) {
                if ($a->starts_at < $b->starts_at->copy()->addMinutes($b->duration) && $b->starts_at < $a->starts_at->copy()->addMinutes($a->duration) && ($a->track === $b->track || ($a->assignee_id && $a->assignee_id === $b->assignee_id) || ($a->project_vendor_id && $a->project_vendor_id === $b->project_vendor_id))) {
                    $out[] = $a->title.' bertabrakan dengan '.$b->title;
                }
            }
        }

        return $out;
    }

    public function run(Project $p, User $u, string $op, int $id, array $d): void
    {
        DB::transaction(function () use ($p, $u, $op, $id, $d) {
            $item = in_array($op, ['rundown-move', 'rundown-edit', 'rundown-remove'], true) ? RundownItem::findOrFail($id) : null;
            $r = Rundown::where('project_id', $p->id)->lockForUpdate()->findOrFail($item?->rundown_id ?? $id);
            if ($op === 'revise-rundown') {
                $p->newQuery()->whereKey($p->id)->lockForUpdate()->first();
                $next = Rundown::create(['project_id' => $p->id, 'title' => $r->title, 'visibility' => $r->visibility, 'version' => Rundown::where('project_id', $p->id)->max('version') + 1]);
                foreach ($r->items as $row) {
                    $next->items()->create($row->only(['title', 'starts_at', 'duration', 'position', 'location', 'track', 'assignee_id', 'project_vendor_id', 'notes']));
                }
                $r = $next;
            } else {
                if ($r->status !== 'draft') {
                    throw ValidationException::withMessages(['rundown' => 'Versi final tidak dapat diedit. Buat revisi.']);
                }
                if ($op === 'rundown-item') {
                    $r->items()->create([...$d, 'starts_at' => Carbon::parse($d['starts_at'], $p->timezone)->utc(), 'position' => ($r->items()->max('position') ?? 0) + 1]);
                }
                if ($op === 'rundown-edit') {
                    $item->update([...$d, 'starts_at' => Carbon::parse($d['starts_at'], $p->timezone)->utc()]);
                }
                if ($op === 'rundown-remove') {
                    $item->delete();
                }
                if ($op === 'finalize') {
                    if (! $r->items()->exists()) {
                        throw ValidationException::withMessages(['rundown' => 'Tambahkan kegiatan sebelum finalisasi.']);
                    }
                    $r->update(['status' => 'final']);
                }
                if ($op === 'rundown-move') {
                    $other = $r->items()->where('position', $d['direction'] === 'up' ? '<' : '>', $item->position)->reorder()->orderBy('position', $d['direction'] === 'up' ? 'desc' : 'asc')->first();
                    if ($other) {
                        $old = $item->position;
                        $item->update(['position' => $other->position]);
                        $other->update(['position' => $old]);
                    }
                }
            }
            Audit::record($u, $p, match ($op) {
                'rundown-item' => 'Kegiatan rundown ditambahkan', 'rundown-edit' => 'Kegiatan rundown diperbarui', 'rundown-remove' => 'Kegiatan draft dihapus', 'rundown-move' => 'Urutan rundown diubah', 'finalize' => 'Rundown difinalisasi', 'revise-rundown' => 'Versi rundown baru dibuat'
            }, $r, ['version' => $r->version]);
        });
    }
}
