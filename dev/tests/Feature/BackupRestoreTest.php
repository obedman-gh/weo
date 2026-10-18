<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ClientInvoice;
use App\Models\Document;
use App\Models\Project;
use App\Models\Rundown;
use App\Models\User;
use App\Models\VendorObligation;
use App\Services\ApprovalAction;
use App\Services\PaymentAction;
use App\Services\RundownAction;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    public function test_sql_dump_and_private_files_restore_with_permissions_and_history(): void
    {
        $dump = $this->executable('mariadb-dump');
        $client = $this->executable('mariadb');
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $project = Project::orderBy('id')->firstOrFail();
        $pic = $project->members()->where('role', 'pic')->firstOrFail();
        $couple = $project->members()->where('role', 'client')->get();
        $approval = Approval::where('project_id', $project->id)->firstOrFail();
        $approvedDocumentVersion = $approval->document_version_id;
        $document = Document::where('project_id', $project->id)->where('visibility', 'client')->firstOrFail();
        $newPath = 'restore-test/konsep-versi-2.txt';
        Storage::disk('local')->put($newPath, 'Materi versi kedua; approval tetap mengacu versi pertama.');
        $document->versions()->create(['version' => 2, 'path' => $newPath, 'original_name' => 'konsep-v2.txt', 'mime' => 'text/plain', 'size' => Storage::disk('local')->size($newPath), 'uploaded_by' => $pic->id]);
        foreach ($couple as $user) {
            app(ApprovalAction::class)->run($project, $user, $approval->id, 'decide', ['decision' => 'disetujui', 'comment' => 'Disetujui sebelum backup.']);
        }
        $invoice = ClientInvoice::where('project_id', $project->id)->firstOrFail();
        app(PaymentAction::class)->decide($project, $pic, $invoice->payments()->where('status', 'menunggu')->firstOrFail()->id, false);
        $balance = PaymentAction::balance($invoice);
        $obligation = VendorObligation::where('project_id', $project->id)->firstOrFail();
        $vendorBalance = PaymentAction::balance($obligation);
        $rundown = Rundown::where('project_id', $project->id)->firstOrFail();
        app(RundownAction::class)->run($project, $pic, 'finalize', $rundown->id, []);

        $backupRoot = storage_path('framework/testing/backup-restore');
        $backup = $backupRoot.'/'.Str::uuid();
        File::ensureDirectoryExists($backup);
        $privateRoot = Storage::disk('local')->path('');
        $this->assertSafeTestPath($privateRoot, storage_path('framework/testing/disks'));

        try {
            $fingerprints = $this->fingerprints();
            $fileHashes = [];
            foreach (Storage::disk('local')->allFiles() as $path) {
                $fileHashes[$path] = hash_file('sha256', Storage::disk('local')->path($path));
            }
            $this->assertNotEmpty($fileHashes);
            $this->assertTrue(File::copyDirectory($privateRoot, $backup.'/files'));

            $database = config('database.connections.mysql');
            $arguments = ['--protocol=TCP', '--host='.$database['host'], '--port='.$database['port'], '--user='.$database['username']];
            $environment = ['MYSQL_PWD' => $database['password']];
            $dumper = new Process([$dump, ...$arguments, '--single-transaction', '--no-tablespaces', '--skip-comments', '--result-file='.$backup.'/database.sql', 'weo_test'], base_path(), $environment);
            $dumper->setTimeout(30)->mustRun();
            $this->assertGreaterThan(1000, filesize($backup.'/database.sql'));

            // Hanya database dan storage fake test yang boleh dikosongkan.
            $this->assertSame('weo_test', DB::connection()->getDatabaseName());
            $this->assertSame('weo_test', $database['username']);
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (Schema::getTableListing() as $table) {
                    Schema::drop($table);
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            $this->assertSame([], Schema::getTableListing());
            File::cleanDirectory($privateRoot);
            $this->assertSame([], Storage::disk('local')->allFiles());

            $input = fopen($backup.'/database.sql', 'rb');
            try {
                $restore = new Process([$client, ...$arguments, '--database=weo_test'], base_path(), $environment);
                $restore->setInput($input)->setTimeout(30)->mustRun();
            } finally {
                fclose($input);
            }
            $this->assertTrue(File::copyDirectory($backup.'/files', $privateRoot));
            $this->assertSame($fingerprints, $this->fingerprints());
            foreach ($fileHashes as $path => $hash) {
                $this->assertSame($hash, hash_file('sha256', Storage::disk('local')->path($path)));
            }
            $this->assertSame($balance, PaymentAction::balance($invoice->fresh()));
            $this->assertSame($vendorBalance, PaymentAction::balance($obligation->fresh()));
            $this->assertSame('disetujui', $approval->fresh()->status);
            $this->assertSame(2, $approval->fresh()->decisions()->count());
            $this->assertSame($approvedDocumentVersion, $approval->fresh()->document_version_id);
            $this->assertSame(2, $document->versions()->count());
            $this->assertSame('final', $rundown->fresh()->status);

            $this->post('/login', ['email' => $couple[0]->email, 'password' => 'WeoDemo!2026#'])->assertRedirect('/dashboard');
            $shared = Document::where('project_id', $project->id)->where('visibility', 'client')->firstOrFail()->versions()->firstOrFail();
            $download = $this->get('/documents/versions/'.$shared->id.'/download')->assertOk()->assertDownload($shared->original_name);
            $this->assertSame($fileHashes[$shared->path], hash('sha256', $download->streamedContent()));
            $oldVersion = $this->get('/documents/versions/'.$approvedDocumentVersion.'/download')->assertOk();
            $this->assertNotSame($download->streamedContent(), $oldVersion->streamedContent());
            $internal = Document::where('project_id', $project->id)->where('visibility', 'internal')->firstOrFail()->versions()->firstOrFail();
            $this->get('/documents/versions/'.$internal->id.'/download')->assertForbidden();
            $other = User::where('role', 'client')->whereDoesntHave('projects', fn ($q) => $q->where('projects.id', $project->id))->firstOrFail();
            $this->actingAs($other)->get('/documents/versions/'.$shared->id.'/download')->assertForbidden();
            $this->actingAs($couple[0])->get('/projects/'.$project->id.'/report.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->get('/rundowns/'.$rundown->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        } finally {
            $this->assertSafeTestPath($backup, $backupRoot);
            File::deleteDirectory($backup);
        }
    }

    private function fingerprints(): array
    {
        $tables = Schema::getTableListing();
        sort($tables);
        $result = [];
        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
            sort($rows);
            $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
        }

        return $result;
    }

    private function executable(string $name): string
    {
        $local = glob(base_path('../.tools/mariadb/*/bin/'.$name.'.exe'));
        $executable = $local[0] ?? (new ExecutableFinder)->find($name);
        if (! $executable) {
            $this->markTestSkipped('Klien '.$name.' diperlukan untuk uji restore SQL nyata.');
        }

        return $executable;
    }

    private function assertSafeTestPath(string $path, string $root): void
    {
        $resolved = realpath($path);
        $allowed = realpath($root);
        $this->assertNotFalse($resolved);
        $this->assertNotFalse($allowed);
        $this->assertStringStartsWith($allowed.DIRECTORY_SEPARATOR, rtrim($resolved, '/\\').DIRECTORY_SEPARATOR);
    }
}
