<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin', 'pic', 'team', 'client'])->default('client');
            $t->boolean('active')->default(true);
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('couple_name');
            $t->date('event_date');
            $t->string('venue');
            $t->string('timezone')->default('Asia/Makassar');
            $t->enum('status', ['persiapan', 'berjalan', 'selesai', 'arsip'])->default('persiapan');
            $t->timestamps();
            $t->index(['status', 'event_date']);
        });
        Schema::create('project_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->unique(['project_id', 'user_id']);
        });
        Schema::create('couple_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->unique()->constrained()->restrictOnDelete();
            $t->string('bride_name');
            $t->string('groom_name');
            $t->timestamps();
        });
        Schema::create('checklist_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('items');
            $t->timestamps();
        });
        Schema::create('vendors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category');
            $t->string('contact');
            $t->text('services')->nullable();
            $t->text('internal_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('project_vendors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $t->decimal('agreed_price', 15, 2);
            $t->enum('status', ['penjajakan', 'dipesan', 'terkonfirmasi', 'dibatalkan'])->default('penjajakan');
            $t->text('coordination')->nullable();
            $t->text('internal_notes')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'vendor_id']);
        });
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->text('participants');
            $t->text('agenda');
            $t->text('discussion')->nullable();
            $t->text('decisions')->nullable();
            $t->enum('visibility', ['internal', 'client'])->default('internal');
            $t->timestamps();
            $t->index(['project_id', 'starts_at']);
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('meeting_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('category');
            $t->enum('kind', ['checklist', 'task'])->default('task');
            $t->boolean('required')->default(false);
            $t->boolean('applicable')->default(true);
            $t->enum('status', ['belum_mulai', 'berjalan', 'terhambat', 'selesai'])->default('belum_mulai');
            $t->enum('priority', ['rendah', 'normal', 'tinggi'])->default('normal');
            $t->date('due_date');
            $t->text('notes')->nullable();
            $t->text('blocker')->nullable();
            $t->enum('visibility', ['internal', 'client'])->default('internal');
            $t->string('template_key')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'template_key']);
            $t->index(['project_id', 'status', 'due_date']);
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('category');
            $t->enum('visibility', ['internal', 'client'])->default('internal');
            $t->foreignId('task_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('meeting_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('project_vendor_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->index(['project_id', 'visibility']);
        });
        Schema::create('document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['document_id', 'version']);
        });
        Schema::create('rundowns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->unsignedInteger('version');
            $t->enum('status', ['draft', 'final'])->default('draft');
            $t->enum('visibility', ['internal', 'client'])->default('internal');
            $t->timestamps();
            $t->unique(['project_id', 'version']);
        });
        Schema::create('rundown_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rundown_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->unsignedInteger('duration');
            $t->unsignedInteger('position');
            $t->string('location');
            $t->string('track')->default('utama');
            $t->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('project_vendor_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['rundown_id', 'position']);
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->text('material');
            $t->unsignedInteger('version');
            $t->foreignId('previous_id')->nullable()->constrained('approvals')->restrictOnDelete();
            $t->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('rundown_id')->nullable()->constrained()->restrictOnDelete();
            $t->enum('rule', ['salah_satu', 'keduanya']);
            $t->enum('status', ['draft', 'diajukan', 'disetujui', 'perlu_revisi', 'ditolak', 'dibatalkan'])->default('draft');
            $t->date('due_date');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->index(['project_id', 'status']);
        });
        Schema::create('approval_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unique(['approval_id', 'user_id']);
        });
        Schema::create('approval_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->enum('decision', ['disetujui', 'perlu_revisi', 'ditolak']);
            $t->text('comment');
            $t->timestamps();
            $t->unique(['approval_id', 'user_id']);
        });
        Schema::create('approval_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        foreach (['client_invoices', 'vendor_obligations'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('project_id')->constrained()->restrictOnDelete();
                if ($table === 'vendor_obligations') {
                    $t->foreignId('project_vendor_id')->constrained()->restrictOnDelete();
                } $t->string('title');
                $t->decimal('amount', 15, 2);
                $t->date('due_date');
                $t->timestamps();
                $t->index(['project_id', 'due_date']);
            });
        }
        foreach (['client_payments' => 'client_invoice_id', 'vendor_payments' => 'vendor_obligation_id'] as $table => $foreign) {
            Schema::create($table, function (Blueprint $t) use ($foreign) {
                $t->id();
                $t->foreignId($foreign)->constrained()->restrictOnDelete();
                $t->decimal('amount', 15, 2);
                $t->date('paid_on');
                $t->enum('status', ['menunggu', 'terverifikasi', 'dibatalkan'])->default('menunggu');
                $t->string('proof_path');
                $t->string('proof_name');
                $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
                $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->timestamp('verified_at')->nullable();
                $t->text('cancellation_reason')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('workspace_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('url');
            $t->string('dedupe_key')->unique();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('changes')->nullable();
            $t->enum('visibility', ['internal', 'client'])->default('internal');
            $t->timestamps();
            $t->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'workspace_notifications', 'vendor_payments', 'client_payments', 'vendor_obligations', 'client_invoices', 'approval_comments', 'approval_decisions', 'approval_recipients', 'approvals', 'rundown_items', 'rundowns', 'document_versions', 'documents', 'tasks', 'meetings', 'project_vendors', 'vendors', 'checklist_templates', 'couple_profiles', 'project_members', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
