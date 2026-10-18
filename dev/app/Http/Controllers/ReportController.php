<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Rundown;
use App\Services\ProjectSummary;
use App\Services\RundownAction;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function report(Request $r, Project $project, ProjectSummary $summary)
    {
        Gate::authorize('view', $project);
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($r->filled('from') ? ['after_or_equal:from'] : [])]]);

        return $this->pdf(view('pdf.report', ['project' => $project, 'summary' => $summary->build($project, $r->user(), $r->from, $r->to), 'from' => $r->from, 'to' => $r->to, 'client' => $r->user()->role === 'client'])->render(), 'ringkasan-'.$project->id.'.pdf');
    }

    public function rundown(Request $r, Rundown $rundown)
    {
        Gate::authorize('view', $rundown->project);
        abort_if($r->user()->role === 'client' && $rundown->visibility !== 'client', 403);
        $rundown->load('items.assignee:id,name', 'items.projectVendor.vendor:id,name');

        return $this->pdf(view('pdf.rundown', ['project' => $rundown->project, 'rundown' => $rundown, 'conflicts' => RundownAction::conflicts($rundown)])->render(), 'rundown-v'.$rundown->version.'.pdf');
    }

    private function pdf(string $html, string $name)
    {
        $opts = new Options;
        $opts->set('isRemoteEnabled', false);
        $opts->set('isPhpEnabled', false);
        $opts->set('chroot', resource_path('views/pdf'));
        $pdf = new Dompdf($opts);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'private, no-store']);
    }
}
