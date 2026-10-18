<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $p = $this->route('project');
        if (! Gate::allows('view', $p) || $p->status === 'arsip') {
            return false;
        }

        return Gate::allows('manage', $p) || (in_array($this->route('operation'), ['decide', 'comment', 'payment'], true) && $this->user()->role === 'client') || ($this->route('operation') === 'task-status' && $this->user()->role === 'team');
    }

    public function rules(): array
    {
        $p = $this->route('project');
        $text = 'required|string|max:180';
        $note = 'nullable|string|max:10000';
        $date = 'required|date_format:Y-m-d';
        $visibility = ['required', Rule::in(['internal', 'client'])];
        $member = ['nullable', 'integer', Rule::exists('project_members', 'user_id')->where('project_id', $p->id)];
        $vendor = ['required', 'integer', Rule::exists('project_vendors', 'id')->where('project_id', $p->id)];
        $file = ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,docx,xlsx,txt', 'extensions:pdf,jpg,jpeg,png,webp,docx,xlsx,txt'];

        return match ($this->route('operation')) {
            'tasks' => ['title' => $text, 'category' => $text, 'kind' => 'required|in:checklist,task', 'required' => 'required|boolean', 'applicable' => 'required|boolean', 'assignee_id' => $member, 'meeting_id' => ['nullable', Rule::exists('meetings', 'id')->where('project_id', $p->id)], 'status' => 'required|in:belum_mulai,berjalan,terhambat,selesai', 'priority' => 'required|in:rendah,normal,tinggi', 'due_date' => $date, 'notes' => $note, 'blocker' => 'required_if:status,terhambat|nullable|string|max:2000', 'visibility' => $visibility],
            'task-status' => ['status' => 'required|in:belum_mulai,berjalan,terhambat,selesai', 'blocker' => 'required_if:status,terhambat|nullable|string|max:2000'],
            'meetings' => ['title' => $text, 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'participants' => 'required|string|max:2000', 'agenda' => 'required|string|max:10000', 'discussion' => $note, 'decisions' => $note, 'visibility' => $visibility],
            'vendors' => ['vendor_id' => 'required|exists:vendors,id', 'agreed_price' => 'required|integer|min:0|max:9999999999999', 'status' => 'required|in:penjajakan,dipesan,terkonfirmasi,dibatalkan', 'coordination' => $note, 'internal_notes' => $note],
            'vendor-master' => ['name' => $text, 'category' => $text, 'contact' => $text, 'services' => $note, 'internal_notes' => $note],
            'documents' => ['title' => $text, 'category' => 'required|in:kontrak,konsep,invoice,referensi,rundown,lainnya', 'visibility' => $visibility, 'file' => $file, 'task_id' => ['nullable', Rule::exists('tasks', 'id')->where('project_id', $p->id)], 'meeting_id' => ['nullable', Rule::exists('meetings', 'id')->where('project_id', $p->id)], 'project_vendor_id' => ['nullable', Rule::exists('project_vendors', 'id')->where('project_id', $p->id)]],
            'document-version' => ['file' => $file],
            'approvals' => ['title' => $text, 'material' => 'required|string|max:10000', 'rule' => 'required|in:salah_satu,keduanya', 'due_date' => $date, 'document_version_id' => 'nullable|integer', 'rundown_id' => ['nullable', Rule::exists('rundowns', 'id')->where('project_id', $p->id)->where('visibility', 'client')->where('status', 'final')]],
            'decide' => ['decision' => 'required|in:disetujui,perlu_revisi,ditolak', 'comment' => 'required|string|max:5000'],
            'comment' => ['body' => 'required|string|max:5000'],
            'invoices' => ['title' => $text, 'amount' => 'required|integer|min:1|max:9999999999999', 'due_date' => $date],
            'obligations' => ['title' => $text, 'amount' => 'required|integer|min:1|max:9999999999999', 'due_date' => $date, 'project_vendor_id' => $vendor],
            'payment','vendor-payment' => ['amount' => 'required|integer|min:1|max:9999999999999', 'paid_on' => $date, 'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp']],
            'cancel-payment','cancel-vendor-payment' => ['reason' => 'required|string|max:2000'],
            'rundowns' => ['title' => $text, 'visibility' => $visibility],
            'rundown-item','rundown-edit' => ['title' => $text, 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'duration' => 'required|integer|min:1|max:1440', 'location' => $text, 'track' => $text, 'assignee_id' => $member, 'project_vendor_id' => ['nullable', Rule::exists('project_vendors', 'id')->where('project_id', $p->id)], 'notes' => $note],
            'rundown-move' => ['direction' => 'required|in:up,down'],
            'template' => ['name' => $text, 'items' => 'required|array|min:1|max:100', 'items.*' => 'required|array:title,category,visibility', 'items.*.title' => $text, 'items.*.category' => $text, 'items.*.visibility' => 'required|in:internal,client'],
            'apply-template' => ['template_id' => 'required|exists:checklist_templates,id'],
            'rundown-remove','submit','cancel-approval','revise','verify','verify-vendor','finalize','revise-rundown' => [],
            default => ['operation' => 'required|in:invalid'],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('file');
            if ($file && $file->isValid()) {
                $name = $file->getClientOriginalName();
                if (mb_strlen($name) > 180 || preg_match('/[\\x00-\\x1F\\x7F]/u', $name)) {
                    $validator->errors()->add('file', 'Nama berkas maksimal 180 karakter tanpa karakter kontrol.');
                }
            }
        });
    }

    public function messages(): array
    {
        return ['required' => 'Kolom :attribute wajib diisi.', 'required_if' => 'Isi :attribute saat status terhambat.', 'integer' => ':attribute harus berupa bilangan bulat.', 'exists' => 'Pilihan :attribute tidak tersedia untuk proyek ini.', 'file' => 'Pilih berkas yang valid.', 'max' => ':attribute melebihi batas :max.', 'in' => 'Pilihan :attribute tidak valid.', 'mimes' => 'Jenis berkas tidak didukung.', 'extensions' => 'Ekstensi berkas tidak didukung.'];
    }
}
