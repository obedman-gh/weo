<?php

use App\Models\Approval;
use App\Models\ClientInvoice;
use App\Models\Project;
use App\Models\Task;
use App\Services\Audit;
use App\Services\PaymentAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('weo:remind', function () {
    foreach (Project::where('status', '!=', 'arsip')->cursor() as $p) {
        $today = now($p->timezone)->toDateString();
        foreach (Task::where('project_id', $p->id)->where('status', '!=', 'selesai')->whereDate('due_date', '<=', $today)->cursor() as $t) {
            Audit::notify($p, 'Tenggat tugas: '.$t->title, '/projects/'.$p->id.'/next-actions', 'reminder:task:'.$t->id.':'.$today, $t->visibility === 'client', $t->assignee_id);
        }
        foreach (Approval::where('project_id', $p->id)->where('status', 'diajukan')->whereDate('due_date', '<=', $today)->cursor() as $a) {
            Audit::notify($p, 'Persetujuan menunggu: '.$a->title, '/projects/'.$p->id.'/approvals', 'reminder:approval:'.$a->id.':'.$today, true);
        }
        foreach (ClientInvoice::where('project_id', $p->id)->whereDate('due_date', '<=', $today)->cursor() as $i) {
            if (PaymentAction::balance($i) > 0) {
                Audit::notify($p, 'Tagihan jatuh tempo: '.$i->title, '/projects/'.$p->id.'/payments', 'reminder:invoice:'.$i->id.':'.$today, true);
            }
        }
    }
    $this->info('Pengingat diproses tanpa duplikasi untuk tanggal yang sama.');
})->purpose('Membuat pengingat dalam aplikasi');
Schedule::command('weo:remind')->hourly()->withoutOverlapping();
