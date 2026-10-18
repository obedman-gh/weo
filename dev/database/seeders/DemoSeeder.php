<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\ChecklistTemplate;
use App\Models\ClientInvoice;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectVendor;
use App\Models\Rundown;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorObligation;
use App\Services\ApprovalAction;
use App\Services\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Seeder demo hanya boleh di local atau testing.');
        }
        DB::transaction(function () {
            $password = 'WeoDemo!2026#';
            $accounts = [['admin', 'Adelia Pramesti', 'admin'], ['admin2', 'Admin Kedua (Demo)', 'admin'], ['pic1', 'Nadia Kirana', 'pic'], ['pic2', 'Dimas Pratama', 'pic'], ['tim1', 'Fajar Mahesa', 'team'], ['tim2', 'Citra Larasati', 'team'], ['tim3', 'Bima Aditya', 'team']];
            $users = [];
            foreach ($accounts as [$key,$name,$role]) {
                $users[$key] = User::firstOrCreate(['email' => $key.'@weo.test'], ['name' => $name, 'role' => $role, 'active' => true, 'password' => $password]);
            }
            $template = ChecklistTemplate::firstOrCreate(['name' => 'Persiapan Pernikahan Lengkap'], ['items' => [
                ['title' => 'Tentukan konsep pernikahan', 'category' => 'Konsep', 'visibility' => 'client'], ['title' => 'Konfirmasi venue dan kapasitas', 'category' => 'Venue', 'visibility' => 'client'], ['title' => 'Finalisasi dekorasi pelaminan', 'category' => 'Dekorasi', 'visibility' => 'client'], ['title' => 'Konfirmasi daftar tamu', 'category' => 'Tamu', 'visibility' => 'client'], ['title' => 'Koordinasi pembagian kru', 'category' => 'Operasional', 'visibility' => 'internal'], ['title' => 'Finalisasi rundown acara', 'category' => 'Acara', 'visibility' => 'client']]]);
            foreach ([['Laras Dekorasi', 'Dekorasi'], ['Senada Visual', 'Fotografi'], ['Rasa Cerita Catering', 'Katering'], ['Bunga Pagi Florist', 'Florist'], ['Nada Senja Music', 'Hiburan'], ['Aksara Undangan', 'Undangan'], ['Ayu Makeup Studio', 'Tata rias'], ['Serasi Attire', 'Busana']] as $i => [$name,$category]) {
                Vendor::firstOrCreate(['name' => $name], ['category' => $category, 'contact' => '08120000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'services' => 'Layanan '.$category.' untuk acara pernikahan', 'internal_notes' => 'Data vendor fiktif development.']);
            }
            $couples = [['Alya', 'Raka', 'Taman Anggrek Convention Hall', 45], ['Nadira', 'Farhan', 'Grand Samudra Ballroom', 75], ['Intan', 'Aditya', 'Rumah Kebun Makassar', 110]];
            foreach ($couples as $i => [$bride,$groom,$venue,$days]) {
                $clients = [];
                foreach (['bride' => $bride, 'groom' => $groom] as $key => $name) {
                    $clients[] = User::firstOrCreate(['email' => $key.($i + 1).'@weo.test'], ['name' => $name.' (Demo)', 'role' => 'client', 'active' => true, 'password' => $password]);
                }
                $p = Project::firstOrCreate(['name' => 'Pernikahan '.$bride.' & '.$groom], ['couple_name' => $bride.' & '.$groom, 'event_date' => today('Asia/Makassar')->addDays($days)->toDateString(), 'venue' => $venue, 'timezone' => 'Asia/Makassar', 'status' => 'persiapan']);
                $p->couple()->firstOrCreate([], ['bride_name' => $bride, 'groom_name' => $groom]);
                $pic = $users[$i === 1 ? 'pic2' : 'pic1'];
                $team = $users['tim'.($i + 1)];
                $p->members()->syncWithoutDetaching([$users['admin']->id, $pic->id, $team->id, ...array_map(fn ($u) => $u->id, $clients)]);
                foreach ($template->items as $j => $item) {
                    Task::firstOrCreate(['project_id' => $p->id, 'template_key' => $template->id.':'.$j], [...$item, 'kind' => 'checklist', 'required' => true, 'assignee_id' => $j === 4 ? $team->id : $pic->id, 'status' => $j < 2 ? 'selesai' : ($j === 2 ? 'terhambat' : 'berjalan'), 'blocker' => $j === 2 ? 'Menunggu revisi pilihan warna bunga.' : null, 'priority' => $j === 2 ? 'tinggi' : 'normal', 'due_date' => today('Asia/Makassar')->addDays($j - 3)->toDateString()]);
                }
                $meeting = Meeting::firstOrCreate(['project_id' => $p->id, 'title' => 'Koordinasi konsep dan kebutuhan pasangan'], ['starts_at' => now()->addDays(2), 'participants' => $bride.', '.$groom.', '.$pic->name, 'agenda' => 'Bahas konsep, tamu, dan dekorasi.', 'discussion' => 'Pasangan memilih nuansa ivory dan dusty rose.', 'decisions' => 'Vendor menyampaikan revisi konsep sebelum finalisasi.', 'visibility' => 'client']);
                Task::firstOrCreate(['project_id' => $p->id, 'title' => 'Kirim revisi konsep dekorasi'], ['category' => 'Dekorasi', 'kind' => 'task', 'assignee_id' => $team->id, 'meeting_id' => $meeting->id, 'status' => 'berjalan', 'priority' => 'tinggi', 'due_date' => today('Asia/Makassar')->subDay()->toDateString(), 'visibility' => 'client']);
                Task::firstOrCreate(['project_id' => $p->id, 'title' => 'Siapkan briefing kru'], ['category' => 'Operasional', 'kind' => 'task', 'assignee_id' => $team->id, 'status' => 'belum_mulai', 'priority' => 'normal', 'due_date' => today('Asia/Makassar')->addDays(3)->toDateString(), 'visibility' => 'internal']);
                foreach (Vendor::take(3)->get() as $v) {
                    $pv = ProjectVendor::firstOrCreate(['project_id' => $p->id, 'vendor_id' => $v->id], ['agreed_price' => 15000000, 'status' => 'terkonfirmasi', 'coordination' => 'Koordinasikan jadwal loading dengan PIC.', 'internal_notes' => 'Kesepakatan internal proyek.']);
                    $ob = VendorObligation::firstOrCreate(['project_id' => $p->id, 'project_vendor_id' => $pv->id, 'title' => 'DP '.$v->name], ['amount' => 5000000, 'due_date' => today()->subDays(2)->toDateString()]);
                    if (! $ob->payments()->exists()) {
                        $path = 'demo/'.$p->id.'/vendor-'.$v->id.'.txt';
                        Storage::disk('local')->put($path, 'Bukti transaksi vendor fiktif untuk development.');
                        $ob->payments()->create(['amount' => 2000000, 'paid_on' => today()->toDateString(), 'status' => 'terverifikasi', 'proof_path' => $path, 'proof_name' => 'bukti-demo.txt', 'submitted_by' => $pic->id, 'verified_by' => $users['admin']->id, 'verified_at' => now()]);
                    }
                }
                foreach (['client', 'internal'] as $visibility) {
                    $doc = Document::firstOrCreate(['project_id' => $p->id, 'title' => $visibility === 'client' ? 'Konsep pernikahan awal' : 'Catatan operasional internal'], ['category' => 'konsep', 'visibility' => $visibility, 'meeting_id' => $meeting->id]);
                    if (! $doc->versions()->exists()) {
                        $path = 'demo/'.$p->id.'/konsep-'.$visibility.'.txt';
                        Storage::disk('local')->put($path, "DOKUMEN FIKTIF DEVELOPMENT\nKonsep ".$bride.' & '.$groom."\nNuansa ivory, burgundy, dusty rose.\nVisibilitas: ".$visibility);
                        $doc->versions()->create(['version' => 1, 'path' => $path, 'original_name' => 'konsep-'.$visibility.'.txt', 'mime' => 'text/plain', 'size' => Storage::disk('local')->size($path), 'uploaded_by' => $pic->id]);
                    }
                }
                $invoice = ClientInvoice::firstOrCreate(['project_id' => $p->id, 'title' => 'Termin 1 · Persiapan awal'], ['amount' => 30000000, 'due_date' => today()->subDays(5)->toDateString()]);
                if (! $invoice->payments()->exists()) {
                    $path = 'demo/'.$p->id.'/bukti-klien.txt';
                    Storage::disk('local')->put($path, 'Bukti pembayaran fiktif development.');
                    $invoice->payments()->create(['amount' => 10000000, 'paid_on' => today()->subDays(3)->toDateString(), 'status' => 'terverifikasi', 'proof_path' => $path, 'proof_name' => 'bukti-demo.txt', 'submitted_by' => $clients[0]->id, 'verified_by' => $pic->id, 'verified_at' => now()]);
                    $invoice->payments()->create(['amount' => 5000000, 'paid_on' => today()->toDateString(), 'status' => 'menunggu', 'proof_path' => $path, 'proof_name' => 'bukti-demo.txt', 'submitted_by' => $clients[1]->id]);
                }
                ClientInvoice::firstOrCreate(['project_id' => $p->id, 'title' => 'Termin 2 · Pelaksanaan acara'], ['amount' => 45000000, 'due_date' => $p->event_date->copy()->subDays(14)->toDateString()]);
                $approval = Approval::firstOrCreate(['project_id' => $p->id, 'title' => 'Pilihan konsep dekorasi', 'version' => 1], ['material' => 'Ivory dengan dusty rose dan aksen champagne. Mohon keputusan kedua pasangan untuk versi konsep ini.', 'rule' => 'keduanya', 'status' => 'draft', 'due_date' => today()->addDays(2)->toDateString(), 'created_by' => $pic->id, 'document_version_id' => Document::where('project_id', $p->id)->where('visibility', 'client')->first()->versions()->first()->id]);
                if (! $approval->submitted_at && $approval->wasRecentlyCreated) {
                    app(ApprovalAction::class)->run($p, $pic, $approval->id, 'submit', []);
                }
                $rundown = Rundown::firstOrCreate(['project_id' => $p->id, 'version' => 1], ['title' => 'Rundown hari pernikahan', 'status' => 'draft', 'visibility' => 'client']);
                if (! $rundown->items()->exists()) {
                    foreach ([['Persiapan dan briefing', 60, 'utama'], ['Penerimaan keluarga', 30, 'utama'], ['Sesi foto keluarga', 45, 'foto'], ['Prosesi akad', 60, 'utama']] as $j => [$title,$duration,$track]) {
                        $rundown->items()->create(['title' => $title, 'starts_at' => $p->event_date->copy()->setTime(0, 0)->addMinutes($j * 60), 'duration' => $duration, 'position' => $j + 1, 'location' => $venue, 'track' => $track, 'assignee_id' => $pic->id, 'notes' => 'Koordinasi melalui PIC acara.']);
                    }
                }
                if ($p->wasRecentlyCreated) {
                    Audit::record($users['admin'], $p, 'Data demo proyek disiapkan', $p);
                }
            }
        });
    }
}
