<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalAction;
use App\Services\PaymentAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'weo_test' || config('database.connections.mysql.username') !== 'weo_test') {
    exit(2);
}
$job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$barrierRoot = realpath(storage_path('framework/testing/concurrency'));
if (! $barrierRoot || realpath(dirname($job['barrier'])) !== $barrierRoot) {
    exit(2);
}
$project = Project::findOrFail($job['project_id']);
$user = User::findOrFail($job['user_id']);
$ready = function () use ($job): void {
    file_put_contents($job['barrier'].'/'.$job['index'].'.ready', 'ready');
    $deadline = microtime(true) + 15;
    while (! is_file($job['barrier'].'/go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Batas tunggu sinkronisasi terlewati.');
        }
        usleep(10000);
        clearstatcache();
    }
};

try {
    if ($job['kind'] === 'payment') {
        $observed = false;
        $table = $job['vendor'] ? 'vendor_payments' : 'client_payments';
        DB::listen(function (QueryExecuted $query) use (&$observed, $table, $ready): void {
            if (! $observed && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '`'.$table.'`')) {
                $observed = true;
                $ready();
            }
        });
        app(PaymentAction::class)->decide($project, $user, $job['id'], $job['vendor']);
    } else {
        $ready();
        app(ApprovalAction::class)->run($project, $user, $job['id'], 'decide', ['decision' => 'disetujui', 'comment' => 'Keputusan uji serentak.']);
    }
    echo json_encode(['result' => 'ok']);
} catch (ValidationException) {
    echo json_encode(['result' => 'invalid']);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Proses uji gagal: '.$exception::class.' '.$exception->getMessage());
    exit(1);
}
