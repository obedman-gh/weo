<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ClientInvoice;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorObligation;
use App\Services\ApprovalAction;
use App\Services\PaymentAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentTransactionsTest extends TestCase
{
    use DatabaseMigrations;

    private Project $project;

    private User $pic;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pic = User::factory()->create(['role' => 'pic']);
        $this->project = Project::factory()->create();
        $this->project->members()->attach($this->pic);
        $this->barrier = storage_path('framework/testing/concurrency/'.Str::uuid());
        File::ensureDirectoryExists($this->barrier);
    }

    protected function tearDown(): void
    {
        if (isset($this->barrier)) {
            File::deleteDirectory($this->barrier);
        }
        parent::tearDown();
    }

    public function test_concurrent_client_verifications_cannot_overpay(): void
    {
        $this->paymentRace(false);
    }

    public function test_concurrent_vendor_verifications_cannot_overpay(): void
    {
        $this->paymentRace(true);
    }

    private function paymentRace(bool $vendor): void
    {
        if ($vendor) {
            $master = Vendor::create(['name' => 'Vendor uji', 'category' => 'Acara', 'contact' => 'Kontak fiktif']);
            $assignment = ProjectVendor::create(['project_id' => $this->project->id, 'vendor_id' => $master->id, 'agreed_price' => 100000]);
            $invoice = VendorObligation::create(['project_id' => $this->project->id, 'project_vendor_id' => $assignment->id, 'title' => 'Uji serentak', 'amount' => 100000, 'due_date' => today()]);
        } else {
            $invoice = ClientInvoice::create(['project_id' => $this->project->id, 'title' => 'Uji serentak', 'amount' => 100000, 'due_date' => today()]);
        }

        $jobs = [];
        for ($i = 0; $i < 2; $i++) {
            $payment = $invoice->payments()->create(['amount' => 80000, 'paid_on' => today(), 'proof_path' => 'tidak-dibuka.pdf', 'proof_name' => 'uji.pdf', 'submitted_by' => $this->pic->id]);
            $jobs[] = ['kind' => 'payment', 'vendor' => $vendor, 'id' => $payment->id, 'user_id' => $this->pic->id];
        }

        // Tahan tagihan agar dua proses sama-sama mencapai pembacaan awal pembayaran.
        DB::beginTransaction();
        $invoice->newQuery()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        try {
            $results = $this->race($jobs, true);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
        }

        sort($results);
        $this->assertSame(['invalid', 'ok'], $results);
        $this->assertSame(20000, PaymentAction::balance($invoice->fresh()));
        $this->assertSame(1, $invoice->payments()->where('status', 'terverifikasi')->count());
    }

    public function test_concurrent_partner_decisions_complete_both_partner_rule(): void
    {
        $clients = User::factory()->count(2)->create(['role' => 'client']);
        $this->project->members()->attach($clients->pluck('id'));
        $approval = Approval::create(['project_id' => $this->project->id, 'title' => 'Konsep', 'material' => 'Versi uji', 'version' => 1, 'rule' => 'keduanya', 'due_date' => today(), 'created_by' => $this->pic->id]);
        app(ApprovalAction::class)->run($this->project, $this->pic, $approval->id, 'submit', []);
        $jobs = $clients->map(fn ($user) => ['kind' => 'approval', 'id' => $approval->id, 'user_id' => $user->id])->all();
        $this->assertSame(['ok', 'ok'], $this->race($jobs));
        $this->assertSame('disetujui', $approval->fresh()->status);
        $this->assertSame(2, $approval->decisions()->count());
    }

    public function test_concurrent_duplicate_decision_is_rejected(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->project->members()->attach($client);
        $approval = Approval::create(['project_id' => $this->project->id, 'title' => 'Konsep', 'material' => 'Versi uji', 'version' => 1, 'rule' => 'salah_satu', 'due_date' => today(), 'created_by' => $this->pic->id]);
        app(ApprovalAction::class)->run($this->project, $this->pic, $approval->id, 'submit', []);
        $job = ['kind' => 'approval', 'id' => $approval->id, 'user_id' => $client->id];
        $results = $this->race([$job, $job]);
        sort($results);
        $this->assertSame(['invalid', 'ok'], $results);
        $this->assertSame(1, $approval->decisions()->count());
    }

    private function race(array $jobs, bool $releaseInvoice = false): array
    {
        $workers = [];
        try {
            foreach ($jobs as $index => $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/transaction-worker.php')], base_path(), ['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array']);
                $worker->setInput(json_encode([...$job, 'project_id' => $this->project->id, 'barrier' => $this->barrier, 'index' => $index], JSON_THROW_ON_ERROR));
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }

            $deadline = microtime(true) + 12;
            while (! is_file($this->barrier.'/0.ready') || ! is_file($this->barrier.'/1.ready')) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail('Proses uji berhenti sebelum siap: '.$worker->getOutput().$worker->getErrorOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Dua proses belum mencapai titik sinkronisasi.');
                }
                usleep(10000);
                clearstatcache();
            }
            if ($releaseInvoice) {
                DB::commit();
            }
            file_put_contents($this->barrier.'/go', 'go');

            return array_map(function (Process $worker): string {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                return $result['result'];
            }, $workers);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }
}
