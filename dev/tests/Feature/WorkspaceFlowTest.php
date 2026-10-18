<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\ChecklistTemplate;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\Rundown;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorObligation;
use App\Models\WorkspaceNotification;
use App\Services\PaymentAction;
use App\Services\ProjectSummary;
use App\Services\RundownAction;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_templates_are_only_sent_to_project_administrators(): void
    {
        ChecklistTemplate::create(['name' => 'Panduan internal', 'items' => [['title' => 'Persiapan internal rahasia', 'category' => 'Tim', 'visibility' => 'internal']]]);
        $this->actingAs($this->bride)->get('/projects')->assertInertia(fn (Assert $page) => $page->has('templates', 0)->has('users', 0));
        $this->actingAs($this->team)->get('/projects')->assertInertia(fn (Assert $page) => $page->has('templates', 0));
        $this->actingAs($this->admin)->get('/projects')->assertInertia(fn (Assert $page) => $page->has('templates', 1));
    }

    public function test_template_items_cannot_carry_unvalidated_project_fields(): void
    {
        $this->actingAs($this->admin);
        $this->action('template', ['name' => 'Tidak sah', 'items' => [['title' => 'Item', 'category' => 'Umum', 'visibility' => 'client', 'project_id' => $this->other->id]]])->assertUnprocessable()->assertJsonValidationErrors('items.0');
        $this->assertDatabaseMissing('checklist_templates', ['name' => 'Tidak sah']);
    }

    public function test_project_search_rejects_non_text_query(): void
    {
        $this->actingAs($this->admin)->getJson('/projects?q[]=salah')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    private User $admin;

    private User $pic;

    private User $team;

    private User $bride;

    private User $groom;

    private User $outsider;

    private Project $p;

    private Project $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['admin' => 'admin', 'pic' => 'pic', 'team' => 'team', 'bride' => 'client', 'groom' => 'client', 'outsider' => 'client'] as $key => $role) {
            $this->$key = User::factory()->create(['name' => $key, 'role' => $role, 'active' => true]);
        }
        $this->p = Project::create(['name' => 'Uji Pernikahan', 'couple_name' => 'A & B', 'event_date' => '2027-01-10', 'venue' => 'Gedung Uji', 'timezone' => 'Asia/Makassar']);
        $this->other = Project::create(['name' => 'Proyek Lain', 'couple_name' => 'C & D', 'event_date' => '2027-02-10', 'venue' => 'Gedung Lain', 'timezone' => 'Asia/Makassar']);
        $this->p->members()->attach([$this->pic->id, $this->team->id, $this->bride->id, $this->groom->id]);
        $this->other->members()->attach($this->outsider->id);
    }

    private function action(string $op, array $data = [], ?int $id = null, ?Project $p = null)
    {
        return $this->postJson('/projects/'.($p ?? $this->p)->id.'/actions/'.$op.($id ? '/'.$id : ''), $data);
    }

    private function task(array $d = []): Task
    {
        return Task::create(['project_id' => $this->p->id, 'title' => 'Tugas contoh', 'category' => 'Acara', 'due_date' => today()->toDateString(), 'assignee_id' => $this->team->id, 'kind' => 'checklist', 'required' => true, 'visibility' => 'client', ...$d]);
    }

    private function approval(array $d = []): Approval
    {
        return Approval::create(['project_id' => $this->p->id, 'title' => 'Konsep', 'material' => 'Materi v1', 'rule' => 'keduanya', 'status' => 'draft', 'version' => 1, 'due_date' => today()->toDateString(), 'created_by' => $this->pic->id, ...$d]);
    }

    private function invoice(int $amount = 100000): ClientInvoice
    {
        return ClientInvoice::create(['project_id' => $this->p->id, 'title' => 'Termin', 'amount' => $amount, 'due_date' => today()->subDay()->toDateString()]);
    }

    private function uploadPayment(ClientInvoice $i, int $amount): ClientPayment
    {
        $this->actingAs($this->bride);
        $this->action('payment', ['amount' => $amount, 'paid_on' => today()->toDateString(), 'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')], $i->id)->assertRedirect();

        return $i->payments()->latest('id')->firstOrFail();
    }

    public function test_guest_is_redirected_and_public_registration_is_absent(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
    }

    public function test_login_rate_limit_and_logout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'salah@example.test', 'password' => 'salah'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'salah@example.test', 'password' => 'salah'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($this->admin)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_creates_project_and_template_is_a_snapshot(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template', 'items' => [['title' => 'Item asli', 'category' => 'Umum', 'visibility' => 'client']]]);
        $this->actingAs($this->admin)->postJson('/projects', ['name' => 'Baru', 'couple_name' => 'E & F', 'bride_name' => 'E', 'groom_name' => 'F', 'event_date' => '2027-04-01', 'venue' => 'Venue', 'timezone' => 'Asia/Makassar', 'status' => 'persiapan', 'member_ids' => [$this->pic->id, $this->bride->id, $this->groom->id], 'template_id' => $template->id])->assertRedirect();
        $new = Project::where('name', 'Baru')->firstOrFail();
        $this->assertTrue($new->members->contains($this->pic));
        $this->assertEquals('Item asli', $new->tasks->first()->title);
        $template->update(['items' => [['title' => 'Berubah', 'category' => 'Lain', 'visibility' => 'internal']]]);
        $this->assertEquals('Item asli', $new->tasks()->first()->title);
        $this->actingAs($this->pic)->postJson('/projects', [])->assertForbidden();
    }

    public function test_all_project_modules_reject_other_project_client(): void
    {
        foreach (['overview', 'progress', 'tasks', 'approvals', 'next-actions', 'vendors', 'payments', 'vendor-payments', 'documents', 'meetings', 'rundowns', 'reports'] as $module) {
            $this->actingAs($this->outsider)->get('/projects/'.$this->p->id.'/'.$module)->assertForbidden();
        }
        $this->actingAs($this->outsider)->get('/projects/'.$this->p->id.'/report.pdf')->assertForbidden();
        $this->action('tasks', [])->assertForbidden();
    }

    public function test_progress_counts_only_required_applicable_items_and_filters_client_visibility(): void
    {
        $this->assertNull($this->p->progress());
        $this->task(['status' => 'selesai']);
        $this->task(['status' => 'berjalan']);
        $this->task(['status' => 'selesai', 'required' => false]);
        $this->task(['status' => 'selesai', 'applicable' => false]);
        $this->task(['status' => 'selesai', 'visibility' => 'internal']);
        $this->assertEquals(67, $this->p->progress());
        $this->assertEquals(50, $this->p->progress($this->bride));
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/progress')->assertInertia(fn (Assert $p) => $p->where('summary.progress', 50)->has('records.data', 4));
    }

    public function test_team_can_only_update_status_of_own_task(): void
    {
        $own = $this->task();
        $other = $this->task(['assignee_id' => $this->pic->id]);
        $this->actingAs($this->team);
        $this->action('task-status', ['status' => 'selesai', 'title' => 'Tidak boleh berubah'], $own->id)->assertRedirect();
        $this->assertEquals('Tugas contoh', $own->fresh()->title);
        $this->action('task-status', ['status' => 'selesai'], $other->id)->assertForbidden();
        $this->action('tasks', [], $own->id)->assertForbidden();
        $this->action('task-status', ['status' => 'terhambat'], $own->id)->assertUnprocessable();
    }

    public function test_task_foreign_assignee_and_meeting_are_rejected(): void
    {
        $this->actingAs($this->pic);
        $this->action('tasks', ['title' => 'Tugas', 'category' => 'Acara', 'kind' => 'task', 'required' => false, 'applicable' => true, 'assignee_id' => $this->outsider->id, 'status' => 'berjalan', 'priority' => 'tinggi', 'due_date' => '2027-01-01', 'visibility' => 'client'])->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
    }

    public function test_both_partners_must_decide_once_for_same_version(): void
    {
        $a = $this->approval();
        $this->actingAs($this->pic);
        $this->action('submit', [], $a->id)->assertRedirect();
        $this->actingAs($this->bride);
        $this->action('decide', ['decision' => 'disetujui', 'comment' => 'Setuju versi 1'], $a->id)->assertRedirect();
        $this->assertEquals('diajukan', $a->fresh()->status);
        $this->action('decide', ['decision' => 'ditolak', 'comment' => 'Ganda'], $a->id)->assertUnprocessable();
        $this->actingAs($this->groom);
        $this->action('decide', ['decision' => 'disetujui', 'comment' => 'Setuju'], $a->id)->assertRedirect();
        $this->assertEquals('disetujui', $a->fresh()->status);
        $this->assertEquals(2, $a->decisions()->count());
        $this->actingAs($this->pic);
        $this->action('decide', ['decision' => 'ditolak', 'comment' => 'PIC tidak memutuskan'], $a->id)->assertForbidden();
    }

    public function test_one_partner_rule_and_revision_preserve_old_decisions(): void
    {
        $a = $this->approval(['rule' => 'salah_satu']);
        $this->actingAs($this->pic);
        $this->action('submit', [], $a->id)->assertRedirect();
        $this->actingAs($this->bride);
        $this->action('decide', ['decision' => 'perlu_revisi', 'comment' => 'Revisi warna'], $a->id)->assertRedirect();
        $this->actingAs($this->pic);
        $this->action('revise', [], $a->id)->assertRedirect();
        $next = Approval::where('previous_id', $a->id)->firstOrFail();
        $this->assertEquals(2, $next->version);
        $this->assertEquals('draft', $next->status);
        $this->assertEquals(0, $next->decisions()->count());
        $this->assertEquals(1, $a->decisions()->count());
        $this->action('revise', [], $a->id)->assertUnprocessable();
        $this->action('approvals', ['title' => 'Ditimpa', 'material' => 'Isi berbeda', 'rule' => 'salah_satu', 'due_date' => '2027-01-01'], $a->id)->assertUnprocessable();
    }

    public function test_approval_submission_requires_couple_and_client_cannot_view_drafts(): void
    {
        $a = $this->approval();
        $this->p->members()->detach($this->groom);
        $this->actingAs($this->pic);
        $this->action('submit', [], $a->id)->assertUnprocessable();
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/approvals')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->action('comment', ['body' => 'Tidak boleh melihat draft'], $a->id)->assertForbidden();
    }

    public function test_unverified_upload_does_not_increase_paid_and_verification_cannot_repeat(): void
    {
        $i = $this->invoice();
        $pay = $this->uploadPayment($i, 40000);
        $this->assertEquals(100000, PaymentAction::balance($i));
        $this->actingAs($this->pic);
        $this->action('verify', [], $pay->id)->assertRedirect();
        $this->assertEquals(60000, PaymentAction::balance($i));
        $this->action('verify', [], $pay->id)->assertUnprocessable();
        $this->assertEquals(60000, PaymentAction::balance($i));
        $this->actingAs($this->bride);
        $this->action('verify', [], $pay->id)->assertForbidden();
    }

    public function test_pending_payments_cannot_be_verified_above_remaining_balance(): void
    {
        $i = $this->invoice();
        $a = $this->uploadPayment($i, 80000);
        $b = $this->uploadPayment($i, 80000);
        $this->actingAs($this->admin);
        $this->action('verify', [], $a->id)->assertRedirect();
        $this->action('verify', [], $b->id)->assertUnprocessable();
        $this->assertEquals(20000, PaymentAction::balance($i));
        $this->actingAs($this->outsider);
        $this->action('verify', [], $b->id, $this->other)->assertForbidden();
        $this->actingAs($this->admin);
        $this->action('verify', [], $b->id, $this->other)->assertNotFound();
    }

    public function test_cancel_payment_preserves_history_and_restores_balance(): void
    {
        $i = $this->invoice();
        $pay = $this->uploadPayment($i, 100000);
        $this->actingAs($this->pic);
        $this->action('verify', [], $pay->id)->assertRedirect();
        $this->assertEquals(0, PaymentAction::balance($i));
        $this->action('cancel-payment', ['reason' => 'Rekening tujuan perlu koreksi'], $pay->id)->assertRedirect();
        $this->assertEquals(100000, PaymentAction::balance($i));
        $this->assertDatabaseHas('client_payments', ['id' => $pay->id, 'status' => 'dibatalkan']);
        $this->assertTrue(ActivityLog::where('action', 'Pembayaran dibatalkan')->exists());
        $this->action('cancel-payment', ['reason' => 'Ulang'], $pay->id)->assertUnprocessable();
    }

    public function test_finance_rejects_fractions_excess_and_spoofed_status(): void
    {
        $i = $this->invoice();
        $this->actingAs($this->bride);
        foreach ([100001, '1.5', -1] as $value) {
            $this->action('payment', ['amount' => $value, 'paid_on' => '2026-10-18', 'status' => 'terverifikasi', 'file' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')], $i->id)->assertUnprocessable();
        }
        $pay = $this->uploadPayment($i, 100);
        $this->assertEquals('menunggu', $pay->status);
    }

    public function test_vendor_finance_and_internal_notes_never_reach_client_props(): void
    {
        $v = Vendor::create(['name' => 'Vendor', 'category' => 'Dekorasi', 'contact' => 'Rahasia kontak', 'internal_notes' => 'Rahasia master']);
        ProjectVendor::create(['project_id' => $this->p->id, 'vendor_id' => $v->id, 'agreed_price' => 99999999, 'internal_notes' => 'Rahasia harga']);
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/vendors')->assertInertia(fn (Assert $p) => $p->missing('records.data.0.agreed_price')->missing('records.data.0.internal_notes')->missing('records.data.0.vendor.contact')->missing('records.data.0.vendor.internal_notes')->has('summary.vendors', 0)->has('summary.vendor_finance', 0));
        $this->get('/projects/'.$this->p->id.'/vendor-payments')->assertForbidden();
        $this->action('vendor-payment', [], 1)->assertForbidden();
    }

    public function test_private_document_versions_download_and_visibility_are_enforced(): void
    {
        $this->actingAs($this->pic);
        $this->action('documents', ['title' => 'Internal', 'category' => 'kontrak', 'visibility' => 'internal', 'file' => UploadedFile::fake()->create('kontrak.pdf', 10, 'application/pdf')])->assertRedirect();
        $doc = Document::firstOrFail();
        $v = $doc->versions()->firstOrFail();
        $this->actingAs($this->bride)->get('/documents/versions/'.$v->id.'/download')->assertForbidden();
        $this->actingAs($this->outsider)->get('/documents/versions/'.$v->id.'/download')->assertForbidden();
        $this->actingAs($this->pic)->get('/documents/versions/'.$v->id.'/download')->assertOk()->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->action('document-version', ['file' => UploadedFile::fake()->create('kontrak-v2.pdf', 11, 'application/pdf')], $doc->id)->assertRedirect();
        $this->assertEquals(2, $doc->versions()->count());
        $this->action('documents', ['title' => 'Jahat', 'category' => 'konsep', 'visibility' => 'client', 'file' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertUnprocessable();
        $this->get('/storage/'.$v->path)->assertNotFound();
    }

    public function test_approval_cannot_attach_internal_document_material(): void
    {
        $d = Document::create(['project_id' => $this->p->id, 'title' => 'Internal', 'category' => 'konsep', 'visibility' => 'internal']);
        $v = $d->versions()->create(['version' => 1, 'path' => 'internal.txt', 'original_name' => 'internal.txt', 'mime' => 'text/plain', 'size' => 1, 'uploaded_by' => $this->pic->id]);
        $this->actingAs($this->pic);
        $this->action('approvals', ['title' => 'Konsep', 'material' => 'Isi', 'rule' => 'salah_satu', 'due_date' => '2027-01-01', 'document_version_id' => $v->id])->assertForbidden();
    }

    public function test_meeting_followup_is_same_task_source_for_next_action(): void
    {
        $this->actingAs($this->pic);
        $this->action('meetings', ['title' => 'Rapat', 'starts_at' => '2027-01-01T09:00', 'participants' => 'PIC, pasangan', 'agenda' => 'Konsep', 'decisions' => 'Buat revisi', 'visibility' => 'client'])->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $this->assertEquals('01:00', $meeting->starts_at->format('H:i'));
        $task = $this->task(['kind' => 'task', 'meeting_id' => $meeting->id, 'title' => 'Tindak lanjut']);
        $this->get('/projects/'.$this->p->id.'/next-actions')->assertInertia(fn (Assert $p) => $p->where('records.data.0.id', $task->id));
        $task->update(['status' => 'selesai']);
        $this->get('/projects/'.$this->p->id.'/next-actions')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_rundown_supports_midnight_parallel_tracks_and_immutable_final_versions(): void
    {
        $r = Rundown::create(['project_id' => $this->p->id, 'title' => 'Acara', 'version' => 1, 'visibility' => 'client']);
        $this->actingAs($this->pic);
        $this->action('rundown-item', ['title' => 'Tengah malam', 'starts_at' => '2027-01-10T23:45', 'duration' => 60, 'location' => 'Venue', 'track' => 'utama'], $r->id)->assertRedirect();
        $this->action('rundown-item', ['title' => 'Paralel', 'starts_at' => '2027-01-11T00:00', 'duration' => 30, 'location' => 'Taman', 'track' => 'foto'], $r->id)->assertRedirect();
        $this->assertCount(0, RundownAction::conflicts($r->fresh()));
        $this->assertEquals('15:45', $r->items()->first()->starts_at->format('H:i'));
        $this->action('rundown-item', ['title' => 'Bentrok', 'starts_at' => '2027-01-11T00:00', 'duration' => 30, 'location' => 'Venue', 'track' => 'utama'], $r->id)->assertRedirect();
        $this->assertCount(1, RundownAction::conflicts($r->fresh()));
        $this->action('finalize', [], $r->id)->assertRedirect();
        $this->action('rundown-item', ['title' => 'Tidak boleh', 'starts_at' => '2027-01-10T09:00', 'duration' => 30, 'location' => 'Venue', 'track' => 'utama'], $r->id)->assertUnprocessable();
        $this->action('revise-rundown', [], $r->id)->assertRedirect();
        $this->assertEquals(3, Rundown::where('version', 2)->firstOrFail()->items()->count());
        $this->actingAs($this->outsider)->get('/rundowns/'.$r->id.'/pdf')->assertForbidden();
    }

    public function test_pdf_export_is_real_and_respects_client_data_scope(): void
    {
        $this->task();
        $this->invoice();
        $this->actingAs($this->bride);
        $response = $this->get('/projects/'.$this->p->id.'/report.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $s = app(ProjectSummary::class)->build($this->p, $this->bride);
        $this->assertEquals([], $s['vendors']);
        $this->assertEquals([], $s['vendor_finance']);
        $this->get('/projects/'.$this->p->id.'/report.pdf?from=2027-02-01&to=2027-01-01')->assertRedirect();
    }

    public function test_notification_reminders_are_idempotent_and_reads_are_scoped(): void
    {
        $this->task(['due_date' => today()->subDay()->toDateString()]);
        $this->artisan('weo:remind')->assertSuccessful();
        $count = WorkspaceNotification::count();
        $this->artisan('weo:remind')->assertSuccessful();
        $this->assertEquals($count, WorkspaceNotification::count());
        $n = WorkspaceNotification::where('user_id', $this->team->id)->firstOrFail();
        $this->actingAs($this->bride)->post('/notifications/'.$n->id.'/read')->assertForbidden();
        $this->actingAs($this->team)->post('/notifications/'.$n->id.'/read')->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_archived_project_is_readable_but_cannot_be_modified(): void
    {
        $t = $this->task();
        $this->p->update(['status' => 'arsip']);
        $this->actingAs($this->pic)->get('/projects/'.$this->p->id.'/progress')->assertOk();
        $this->action('task-status', ['status' => 'selesai'], $t->id)->assertForbidden();
    }

    public function test_inactive_user_and_removed_membership_lose_access(): void
    {
        $this->pic->update(['active' => false]);
        $this->actingAs($this->pic)->get('/dashboard')->assertRedirect('/login');
        $this->p->members()->detach($this->bride->id);
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/overview')->assertForbidden();
    }

    public function test_seeder_can_repeat_without_duplicate_records(): void
    {
        $this->seed(DemoSeeder::class);
        $counts = [Project::count(), Task::count(), ClientPayment::count(), Document::count(), Approval::count()];
        $this->seed(DemoSeeder::class);
        $this->assertEquals($counts, [Project::count(), Task::count(), ClientPayment::count(), Document::count(), Approval::count()]);
    }

    public function test_vendor_payment_is_separate_from_client_finance(): void
    {
        $v = Vendor::create(['name' => 'Vendor uji', 'category' => 'Acara', 'contact' => 'Kontak']);
        $pv = ProjectVendor::create(['project_id' => $this->p->id, 'vendor_id' => $v->id, 'agreed_price' => 100000]);
        $o = VendorObligation::create(['project_id' => $this->p->id, 'project_vendor_id' => $pv->id, 'title' => 'DP Vendor', 'amount' => 100000, 'due_date' => today()]);
        $invoice = $this->invoice();
        $this->actingAs($this->pic);
        $this->action('vendor-payment', ['amount' => 40000, 'paid_on' => today()->toDateString(), 'file' => UploadedFile::fake()->create('vendor.pdf', 10, 'application/pdf')], $o->id)->assertRedirect();
        $pay = $o->payments()->firstOrFail();
        $this->action('verify-vendor', [], $pay->id)->assertRedirect();
        $this->assertEquals(60000, PaymentAction::balance($o));
        $this->assertEquals(100000, PaymentAction::balance($invoice));
        $this->actingAs($this->bride)->get('/proofs/vendor/'.$pay->id)->assertForbidden();
        $this->actingAs($this->pic);
        $this->action('cancel-vendor-payment', ['reason' => 'Koreksi vendor'], $pay->id)->assertRedirect();
        $this->assertEquals(100000, PaymentAction::balance($o));
    }

    public function test_template_application_is_idempotent(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template tugas', 'items' => [['title' => 'Wajib', 'category' => 'Umum', 'visibility' => 'client']]]);
        $this->actingAs($this->pic);
        $this->action('apply-template', ['template_id' => $template->id])->assertRedirect();
        $this->action('apply-template', ['template_id' => $template->id])->assertRedirect();
        $this->assertEquals(1, Task::where('project_id', $this->p->id)->count());
    }

    public function test_draft_rundown_items_can_move_edit_and_be_removed(): void
    {
        $r = Rundown::create(['project_id' => $this->p->id, 'title' => 'Draft', 'version' => 1, 'visibility' => 'internal']);
        $data = ['title' => 'Awal', 'starts_at' => '2027-01-01T09:00', 'duration' => 30, 'location' => 'Aula', 'track' => 'utama'];
        $this->actingAs($this->pic);
        $this->action('rundown-item', $data, $r->id)->assertRedirect();
        $this->action('rundown-item', [...$data, 'title' => 'Kedua'], $r->id)->assertRedirect();
        $items = $r->items()->get();
        $this->action('rundown-move', ['direction' => 'up'], $items[1]->id)->assertRedirect();
        $this->assertEquals('Kedua', $r->items()->first()->title);
        $this->action('rundown-edit', [...$data, 'title' => 'Diubah'], $items[0]->id)->assertRedirect();
        $this->assertEquals('Diubah', $items[0]->fresh()->title);
        $this->action('rundown-remove', [], $items[0]->id)->assertRedirect();
        $this->assertEquals(1, $r->items()->count());
        $this->actingAs($this->bride)->get('/rundowns/'.$r->id.'/pdf')->assertForbidden();
    }

    public function test_date_period_filters_report_totals(): void
    {
        $a = $this->invoice(1000);
        $a->update(['due_date' => '2027-01-01']);
        $b = $this->invoice(2000);
        $b->update(['due_date' => '2027-02-01']);
        $summary = app(ProjectSummary::class)->build($this->p, $this->bride, '2027-01-01', '2027-01-31');
        $this->assertEquals(1000, $summary['billed']);
        $this->assertCount(1, $summary['invoices']);
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/report.pdf?to=2027-01-31')->assertOk();
    }

    public function test_rundown_client_props_do_not_leak_vendor_agreement(): void
    {
        $v = Vendor::create(['name' => 'Vendor rahasia', 'category' => 'Acara', 'contact' => 'Kontak internal', 'internal_notes' => 'Rahasia master']);
        $pv = ProjectVendor::create(['project_id' => $this->p->id, 'vendor_id' => $v->id, 'agreed_price' => 99999999, 'internal_notes' => 'Rahasia kesepakatan']);
        $r = Rundown::create(['project_id' => $this->p->id, 'title' => 'Acara', 'version' => 1, 'visibility' => 'client']);
        $r->items()->create(['title' => 'Prosesi', 'starts_at' => now(), 'duration' => 60, 'position' => 1, 'location' => 'Venue', 'track' => 'utama', 'project_vendor_id' => $pv->id]);
        $this->actingAs($this->bride)->get('/projects/'.$this->p->id.'/rundowns')->assertInertia(fn (Assert $p) => $p->where('records.data.0.items.0.project_vendor.vendor.name', 'Vendor rahasia')->missing('records.data.0.items.0.project_vendor.agreed_price')->missing('records.data.0.items.0.project_vendor.internal_notes')->missing('records.data.0.items.0.project_vendor.vendor.contact'));
    }

    public function test_long_upload_name_and_fake_extension_are_rejected(): void
    {
        $this->actingAs($this->pic);
        $this->action('documents', ['title' => 'Dokumen', 'category' => 'konsep', 'visibility' => 'client', 'file' => UploadedFile::fake()->create(str_repeat('a', 181).'.pdf', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->action('documents', ['title' => 'Jahat', 'category' => 'konsep', 'visibility' => 'client', 'file' => UploadedFile::fake()->create('jahat.php', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_removed_membership_removes_notifications_and_unread_count(): void
    {
        WorkspaceNotification::create(['project_id' => $this->p->id, 'user_id' => $this->team->id, 'title' => 'Notifikasi lama', 'url' => '/dashboard', 'dedupe_key' => 'removed-membership']);
        $this->p->members()->detach($this->team->id);
        $this->actingAs($this->team)->get('/notifications')->assertInertia(fn (Assert $p) => $p->where('unread', 0)->has('records.data', 0));
    }
}
