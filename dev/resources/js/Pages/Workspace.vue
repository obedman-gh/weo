<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
    Plus,
    ArrowUpRight,
    CalendarDays,
    MapPin,
    Download,
    Search,
    ArrowUp,
    ArrowDown,
    Clock3,
    FolderOpen,
    Flower2,
    Paperclip,
} from "lucide-vue-next";
import AppLayout from "../Layouts/AppLayout.vue";
import FormDialog from "../Components/FormDialog.vue";
import StatusBadge from "../Components/StatusBadge.vue";
import Pagination from "../Components/Pagination.vue";
import { fields, defaults } from "../forms";
import { date, money, label, localInput, daysUntil } from "../format";
import type {
    Project,
    Summary,
    Entity,
    Pagination as Pager,
    Options,
    FormSpec,
    Shared,
    Template,
} from "../types";
const props = defineProps<{
    project: Project;
    module: string;
    manage: boolean;
    summary: Summary;
    records: Pager<Entity> | null;
    options: Options;
    filters: Record<string, string>;
    pendingApprovals?: Entity[];
    outstandingInvoices?: Entity[];
}>();
const user = usePage<Shared>().props.auth.user!;
const client = user.role === "client";
const spec = ref<FormSpec | null>(null);
const normalizeFilter = (v: Record<string, string>) => ({
    q: "",
    status: "",
    priority: "",
    assignee_id: "",
    due: "",
    from: "",
    to: "",
    ...v,
});
const filter = ref(normalizeFilter(props.filters));
watch(
    () => props.filters,
    (v) => (filter.value = normalizeFilter(v)),
);
const titles: Record<string, string> = {
    overview: "Ringkasan proyek",
    progress: "Wedding Progress",
    tasks: "Tugas tim",
    approvals: "Approval Center",
    "next-actions": "Next Action",
    vendors: "Vendor Management",
    payments: "Pembayaran klien",
    "vendor-payments": "Pembayaran vendor",
    documents: "Document Center",
    meetings: "Meeting Notes",
    rundowns: "Rundown Builder",
    reports: "Laporan proyek",
};
const subtitles: Record<string, string> = {
    overview: "Seluruh detail persiapan dalam satu pandangan.",
    progress: "Langkah demi langkah menuju hari yang dinanti.",
    tasks: "Bagikan tanggung jawab, pastikan setiap detail tertangani.",
    approvals: "Setiap keputusan jelas, setiap versi tercatat.",
    "next-actions": "Fokus pada langkah yang paling membutuhkan perhatian.",
    vendors: "Orang-orang pilihan untuk mewujudkan hari istimewa.",
    payments:
        "Pantau termin, bukti bayar, dan pembayaran yang telah diverifikasi.",
    "vendor-payments":
        "Kewajiban dan pembayaran kepada vendor. Khusus internal.",
    documents: "Satu tempat aman untuk semua dokumen persiapan.",
    meetings: "Simpan pembahasan, keputusan, dan langkah berikutnya.",
    rundowns: "Susun alur acara, dari awal hingga momen terakhir.",
    reports: "Ringkasan aktual dari persiapan dan transaksi proyek.",
};
const operation = computed(
    () =>
        (
            ({
                progress: "tasks",
                tasks: "tasks",
                approvals: "approvals",
                vendors: "vendors",
                payments: "invoices",
                "vendor-payments": "obligations",
                documents: "documents",
                meetings: "meetings",
                rundowns: "rundowns",
            }) as Record<string, string>
        )[props.module],
);
const base = computed(() => `/projects/${props.project.id}`);
const reportQuery = computed(() =>
    new URLSearchParams(filter.value).toString(),
);
const heading = computed(() => titles[props.module]);
function act(
    op: string,
    id?: number,
    initial: Record<string, unknown> = {},
    title?: string,
    message?: string,
) {
    const formFields = fields(op, props.options);
    spec.value = {
        title:
            title ??
            (
                {
                    tasks: "Pekerjaan",
                    meetings: "Meeting",
                    vendors: "Penugasan vendor",
                    invoices: "Tagihan klien",
                    obligations: "Kewajiban vendor",
                    documents: "Unggah dokumen",
                    approvals: "Permintaan persetujuan",
                    rundowns: "Rundown baru",
                    payment: "Unggah bukti pembayaran",
                    "vendor-payment": "Catat pembayaran vendor",
                    verify: "Verifikasi pembayaran",
                    "verify-vendor": "Verifikasi pembayaran vendor",
                    submit: "Ajukan persetujuan",
                    finalize: "Finalisasi rundown",
                    revise: "Buat revisi materi",
                    "revise-rundown": "Salin rundown ke versi draft baru",
                } as Record<string, string>
            )[op] ??
            "Simpan perubahan",
        url: `${base.value}/actions/${op}${id ? `/${id}` : ""}`,
        fields: formFields,
        initial: { ...defaults, ...initial },
        message:
            message ??
            (!formFields.length
                ? "Tindakan ini dicatat dalam histori proyek. Lanjutkan?"
                : undefined),
    };
}
function create() {
    act(operation.value, undefined, {
        kind: props.module === "progress" ? "checklist" : "task",
        required: props.module === "progress",
        status:
            props.module === "vendors"
                ? "penjajakan"
                : props.module === "rundowns"
                  ? "draft"
                  : "belum_mulai",
    });
}
function edit(row: Entity, op = operation.value) {
    act(
        op,
        row.id,
        {
            ...row,
            due_date: row.due_date?.slice(0, 10),
            starts_at: localInput(row.starts_at, props.project.timezone),
        },
        "Ubah " + (row.title ?? row.vendor?.name ?? ""),
    );
}
function applyFilters() {
    router.get(`${base.value}/${props.module}`, filter.value, {
        preserveState: true,
        preserveScroll: true,
    });
}
function decisions(row: Entity) {
    return Array.isArray(row.decisions) ? row.decisions : [];
}
function template(t?: Template) {
    act(
        "template",
        t?.id,
        {
            name: t?.name ?? "",
            template_lines:
                t?.items
                    .map((i) => `${i.title} | ${i.category} | ${i.visibility}`)
                    .join("\n") ?? "",
        },
        t ? "Ubah template" : "Buat template",
    );
}
function startApproval(row: Entity) {
    act(
        "approvals",
        undefined,
        {
            title: `Persetujuan ${row.title}`,
            material: `Rundown final versi ${row.version}`,
            rundown_id: row.id,
            rule: "keduanya",
        },
        "Ajukan rundown final",
    );
}
</script>
<template>
    <Head :title="heading" /><AppLayout :project="project" :module="module"
        ><div class="project-context">
            <span>{{ project.couple_name }}</span
            ><span
                ><CalendarDays :size="14" />{{ date(project.event_date) }}</span
            ><span><MapPin :size="14" />{{ project.venue }}</span
            ><StatusBadge :status="project.status" />
        </div>
        <div class="page-heading">
            <div>
                <span class="eyebrow">{{
                    client
                        ? "PERJALANAN MENUJU HARI BAHAGIA"
                        : "RUANG KOORDINASI PROYEK"
                }}</span>
                <h1>{{ heading }}</h1>
                <p class="muted">{{ subtitles[module] }}</p>
            </div>
            <div class="row-actions">
                <button
                    v-if="manage && operation"
                    class="button"
                    @click="create"
                >
                    <Plus :size="17" />{{
                        module === "documents"
                            ? "Unggah dokumen"
                            : "Tambah data"
                    }}</button
                ><a
                    v-if="module === 'reports'"
                    class="button"
                    :href="`${base}/report.pdf?${reportQuery}`"
                    ><Download :size="17" />Unduh PDF</a
                >
            </div>
        </div>
        <div v-if="project.status === 'arsip'" class="info-box">
            Proyek ini diarsipkan. Histori dapat dibaca; perubahan operasional
            dihentikan.
        </div>
        <template v-if="module === 'overview' || module === 'reports'"
            ><section v-if="module === 'overview'" class="project-banner">
                <div>
                    <span class="eyebrow">THE WEDDING OF</span>
                    <h2>{{ project.couple_name }}</h2>
                    <p>
                        {{
                            project.members
                                .filter((m) => m.role === "pic")
                                .map((m) => m.name)
                                .join(", ") || "PIC belum ditugaskan"
                        }}
                        · {{ project.timezone }}
                    </p>
                </div>
                <div class="banner-count">
                    <strong>{{
                        Math.abs(
                            daysUntil(project.event_date, project.timezone),
                        )
                    }}</strong
                    ><span>{{
                        daysUntil(project.event_date, project.timezone) >= 0
                            ? "HARI MENUJU ACARA"
                            : "HARI SEJAK ACARA"
                    }}</span>
                </div>
                <Flower2 class="banner-flower" :size="170" />
            </section>
            <form
                v-if="module === 'reports'"
                class="filter-bar"
                @submit.prevent="applyFilters"
            >
                <label>Dari <input v-model="filter.from" type="date" /></label
                ><label>Sampai <input v-model="filter.to" type="date" /></label
                ><button class="button secondary">Terapkan periode</button>
            </form>
            <section class="stats-grid four">
                <article class="stat-card">
                    <div>
                        <span>Progres persiapan</span><Flower2 :size="18" />
                    </div>
                    <strong>{{
                        summary.progress === null ? "—" : `${summary.progress}%`
                    }}</strong
                    ><small>{{
                        summary.progress === null
                            ? "Belum ada checklist wajib"
                            : "Dari checklist wajib yang berlaku"
                    }}</small>
                    <div class="progress-track">
                        <div :style="{ width: `${summary.progress ?? 0}%` }" />
                    </div>
                </article>
                <article class="stat-card">
                    <div>
                        <span>Pekerjaan selesai</span><Clock3 :size="18" />
                    </div>
                    <strong
                        >{{ summary.tasks.completed
                        }}<small> / {{ summary.tasks.total }}</small></strong
                    ><small
                        >{{ summary.tasks.overdue }} terlambat ·
                        {{ summary.tasks.blocked }} terhambat</small
                    >
                </article>
                <article class="stat-card">
                    <div>
                        <span>Menunggu keputusan</span
                        ><ArrowUpRight :size="18" />
                    </div>
                    <strong>{{ summary.pending_approvals }}</strong
                    ><small>Permintaan persetujuan aktif</small>
                </article>
                <article class="stat-card">
                    <div><span>Sisa tagihan klien</span><span>Rp</span></div>
                    <strong class="money-stat">{{
                        money(summary.balance)
                    }}</strong
                    ><small>Terverifikasi {{ money(summary.paid) }}</small>
                </article>
            </section>
            <div class="two-columns">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Langkah berikutnya</h2>
                        <Link :href="`${base}/next-actions`" class="text-link"
                            >Lihat semua <ArrowUpRight :size="15"
                        /></Link>
                    </div>
                    <div
                        v-for="task in summary.upcoming"
                        :key="task.id"
                        class="task-line"
                    >
                        <span class="task-dot" />
                        <div class="grow">
                            <strong>{{ task.title }}</strong
                            ><small
                                >{{ date(task.due_date) }} ·
                                {{ label(task.priority) }}</small
                            >
                        </div>
                        <StatusBadge :status="task.status" />
                    </div>
                    <p v-if="!summary.upcoming.length" class="empty-text">
                        Semua pekerjaan telah tertangani.
                    </p>
                </section>
                <section class="panel">
                    <div class="panel-head">
                        <h2>Aktivitas terbaru</h2>
                        <span class="eyebrow">HISTORI</span>
                    </div>
                    <div
                        v-for="log in summary.activities"
                        :key="log.id"
                        class="activity-line"
                    >
                        <span class="activity-dot" />
                        <div>
                            <strong>{{ log.action }}</strong
                            ><small
                                >{{ log.user.name }} ·
                                {{
                                    date(log.created_at, project.timezone, true)
                                }}</small
                            >
                        </div>
                    </div>
                    <p v-if="!summary.activities.length" class="empty-text">
                        Belum ada aktivitas yang dapat ditampilkan.
                    </p>
                </section>
            </div>
            <section v-if="module === 'reports'" class="panel">
                <h2>Pembayaran & persetujuan</h2>
                <div class="summary-list">
                    <span
                        >Total tagihan
                        <strong>{{ money(summary.billed) }}</strong></span
                    ><span
                        >Pembayaran sah
                        <strong>{{ money(summary.paid) }}</strong></span
                    ><span
                        v-for="(count, status) in summary.approval_counts"
                        :key="status"
                        >{{ label(String(status)) }}
                        <strong>{{ count }}</strong></span
                    >
                </div>
                <template v-if="['admin', 'pic'].includes(user.role)"
                    ><h3>Vendor internal</h3>
                    <div
                        v-for="v in summary.vendors"
                        :key="v.id"
                        class="row-between table-line"
                    >
                        <span>{{ v.vendor?.name }}</span
                        ><StatusBadge :status="v.status" /><strong>{{
                            money(v.agreed_price)
                        }}</strong>
                    </div></template
                >
            </section></template
        >
        <template v-else
            ><form class="filter-bar" @submit.prevent="applyFilters">
                <Search :size="18" /><input
                    v-model="filter.q"
                    aria-label="Cari data"
                    placeholder="Cari judul atau nama…"
                /><template
                    v-if="
                        ['progress', 'tasks', 'next-actions'].includes(module)
                    "
                    ><select v-model="filter.due" aria-label="Filter tenggat">
                        <option value="">Semua tenggat</option>
                        <option value="mine">Tugas saya</option>
                        <option value="overdue">Terlambat</option>
                        <option value="today">Hari ini</option>
                        <option value="upcoming">Mendatang</option></select
                    ><select
                        v-model="filter.assignee_id"
                        aria-label="Filter PIC"
                    >
                        <option value="">Semua PIC</option>
                        <option
                            v-for="m in options.members"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.name }}
                        </option></select
                    ><select
                        v-model="filter.priority"
                        aria-label="Filter prioritas"
                    >
                        <option value="">Semua prioritas</option>
                        <option value="tinggi">Tinggi</option>
                        <option value="normal">Normal</option>
                        <option value="rendah">Rendah</option></select
                    ><select
                        v-if="module !== 'next-actions'"
                        v-model="filter.status"
                        aria-label="Filter status"
                    >
                        <option value="">Semua status</option>
                        <option
                            v-for="s in [
                                'belum_mulai',
                                'berjalan',
                                'terhambat',
                                'selesai',
                            ]"
                            :key="s"
                            :value="s"
                        >
                            {{ label(s) }}
                        </option>
                    </select></template
                ><select
                    v-if="module === 'approvals'"
                    v-model="filter.status"
                    aria-label="Filter status persetujuan"
                >
                    <option value="">Semua status</option>
                    <option
                        v-for="s in [
                            'draft',
                            'diajukan',
                            'disetujui',
                            'perlu_revisi',
                            'ditolak',
                            'dibatalkan',
                        ]"
                        :key="s"
                        :value="s"
                    >
                        {{ label(s) }}
                    </option></select
                ><button class="button secondary small">Terapkan</button
                ><button
                    type="button"
                    class="text-link"
                    @click="
                        filter = normalizeFilter({});
                        applyFilters();
                    "
                >
                    Reset
                </button>
            </form>
            <div v-if="module === 'progress'" class="progress-overview">
                <div>
                    <strong>{{
                        summary.progress === null
                            ? "Belum ada checklist wajib"
                            : `${summary.progress}% selesai`
                    }}</strong
                    ><small
                        >Item wajib selesai ÷ seluruh item wajib yang
                        berlaku</small
                    >
                </div>
                <div class="progress-track">
                    <div :style="{ width: `${summary.progress ?? 0}%` }" />
                </div>
                <button
                    v-if="manage"
                    class="button secondary small"
                    @click="act('apply-template')"
                >
                    Salin template
                </button>
            </div>
            <div
                v-if="['payments', 'vendor-payments'].includes(module)"
                class="info-box"
            >
                Bukti unggahan berstatus menunggu. Nilai pembayaran baru
                dihitung setelah petugas memverifikasinya. Koreksi tercatat
                melalui pembatalan beralasan.
            </div>
            <div class="record-list">
                <article
                    v-for="row in records?.data"
                    :key="row.id"
                    class="record-card"
                >
                    <div class="record-heading">
                        <div>
                            <span class="eyebrow"
                                >{{
                                    row.category ??
                                    (row.version
                                        ? `VERSI ${row.version}`
                                        : module === "vendors"
                                          ? row.vendor?.category
                                          : row.kind
                                            ? label(row.kind)
                                            : "CATATAN PROYEK")
                                }}<span v-if="row.visibility">
                                    ·
                                    {{
                                        row.visibility === "client"
                                            ? "DIBAGIKAN KE KLIEN"
                                            : "INTERNAL"
                                    }}</span
                                ></span
                            >
                            <h2>{{ row.title ?? row.vendor?.name }}</h2>
                        </div>
                        <StatusBadge
                            v-if="row.status || row.payment_status"
                            :status="row.payment_status ?? row.status"
                        />
                    </div>
                    <template
                        v-if="
                            ['progress', 'tasks', 'next-actions'].includes(
                                module,
                            )
                        "
                        ><div class="inline-meta">
                            <span
                                ><CalendarDays :size="14" />{{
                                    date(row.due_date)
                                }}</span
                            ><span>{{
                                row.assignee?.name ?? "Belum ada PIC"
                            }}</span
                            ><span>Prioritas {{ label(row.priority) }}</span
                            ><span v-if="row.required"
                                >Wajib
                                {{
                                    row.applicable ? "" : "· tidak berlaku"
                                }}</span
                            >
                        </div>
                        <p v-if="row.notes">{{ row.notes }}</p>
                        <p
                            v-if="row.status === 'terhambat'"
                            class="warning-box"
                        >
                            Hambatan: {{ row.blocker }}
                        </p>
                        <div class="row-actions">
                            <button
                                v-if="manage"
                                class="button secondary small"
                                @click="edit(row, 'tasks')"
                            >
                                Ubah pekerjaan</button
                            ><button
                                v-else-if="
                                    user.role === 'team' &&
                                    row.assignee_id === user.id &&
                                    project.status !== 'arsip'
                                "
                                class="button secondary small"
                                @click="edit(row, 'task-status')"
                            >
                                Perbarui status</button
                            ><Link
                                :href="`${base}/documents?q=${encodeURIComponent(row.title ?? '')}`"
                                class="text-link"
                                ><Paperclip :size="14" />Dokumen</Link
                            >
                        </div></template
                    >
                    <template v-if="module === 'approvals'"
                        ><p class="material">{{ row.material }}</p>
                        <div class="inline-meta">
                            <span>{{
                                row.rule === "keduanya"
                                    ? "Keputusan kedua pasangan"
                                    : "Salah satu pasangan"
                            }}</span
                            ><span>Batas {{ date(row.due_date) }}</span
                            ><span v-if="row.recipients?.length">{{
                                row.recipients.map((u) => u.name).join(" & ")
                            }}</span>
                        </div>
                        <p v-if="row.document_version_id">
                            <a
                                class="text-link"
                                :href="`/documents/versions/${row.document_version_id}/download`"
                                >Unduh materi dokumen versi terkait</a
                            >
                        </p>
                        <p v-if="row.rundown_id">
                            <a
                                class="text-link"
                                :href="`/rundowns/${row.rundown_id}/pdf`"
                                >Unduh rundown versi terkait</a
                            >
                        </p>
                        <div
                            v-for="d in decisions(row)"
                            :key="d.id"
                            class="decision-box"
                        >
                            <StatusBadge :status="d.decision" /><strong>{{
                                d.user.name
                            }}</strong
                            ><small>{{
                                date(d.created_at, project.timezone, true)
                            }}</small>
                            <p>{{ d.comment }}</p>
                        </div>
                        <div
                            v-for="c in row.comments"
                            :key="c.id"
                            class="comment"
                        >
                            <strong>{{ c.user.name }}</strong
                            ><small>{{
                                date(c.created_at, project.timezone, true)
                            }}</small>
                            <p>{{ c.body }}</p>
                        </div>
                        <div class="row-actions">
                            <button
                                v-if="manage && row.status === 'draft'"
                                class="button small"
                                @click="act('submit', row.id)"
                            >
                                Ajukan</button
                            ><button
                                v-if="manage && row.status === 'draft'"
                                class="button secondary small"
                                @click="edit(row)"
                            >
                                Ubah draft</button
                            ><button
                                v-if="manage && row.status !== 'draft'"
                                class="button secondary small"
                                @click="act('revise', row.id)"
                            >
                                Buat revisi</button
                            ><button
                                v-if="
                                    manage &&
                                    ['draft', 'diajukan'].includes(
                                        row.status ?? '',
                                    )
                                "
                                class="text-link"
                                @click="act('cancel-approval', row.id)"
                            >
                                Batalkan</button
                            ><button
                                v-if="
                                    client &&
                                    row.status === 'diajukan' &&
                                    row.recipients?.some(
                                        (u) => u.id === user.id,
                                    ) &&
                                    !decisions(row).some(
                                        (d) => d.user.id === user.id,
                                    ) &&
                                    project.status !== 'arsip'
                                "
                                class="button small"
                                @click="act('decide', row.id)"
                            >
                                Beri keputusan</button
                            ><button
                                v-if="
                                    project.status !== 'arsip' &&
                                    (manage || client)
                                "
                                class="button secondary small"
                                @click="act('comment', row.id)"
                            >
                                Komentar
                            </button>
                        </div></template
                    >
                    <template v-if="module === 'vendors'"
                        ><p>{{ row.vendor?.services }}</p>
                        <div class="inline-meta">
                            <span v-if="!client">{{ row.vendor?.contact }}</span
                            ><span v-if="!client"
                                >Kesepakatan {{ money(row.agreed_price) }}</span
                            >
                        </div>
                        <p v-if="row.coordination">
                            Koordinasi: {{ row.coordination }}
                        </p>
                        <p
                            v-if="!client && row.internal_notes"
                            class="internal-note"
                        >
                            Internal: {{ row.internal_notes }}
                        </p>
                        <div class="row-actions">
                            <button
                                v-if="manage"
                                class="button secondary small"
                                @click="edit(row)"
                            >
                                Ubah penugasan</button
                            ><button
                                v-if="manage"
                                class="text-link"
                                @click="
                                    act('documents', undefined, {
                                        title: `Kontrak ${row.vendor?.name}`,
                                        category: 'kontrak',
                                        project_vendor_id: row.id,
                                    })
                                "
                            >
                                Unggah kontrak
                            </button>
                        </div></template
                    >
                    <template
                        v-if="['payments', 'vendor-payments'].includes(module)"
                        ><div class="finance-grid">
                            <div>
                                <small>Nilai tagihan</small
                                ><strong>{{ money(row.amount) }}</strong>
                            </div>
                            <div>
                                <small>Terverifikasi</small
                                ><strong>{{ money(row.paid) }}</strong>
                            </div>
                            <div>
                                <small>Saldo tersisa</small
                                ><strong>{{ money(row.balance) }}</strong>
                            </div>
                            <div>
                                <small>Jatuh tempo</small
                                ><strong>{{ date(row.due_date) }}</strong>
                            </div>
                        </div>
                        <div
                            v-for="pay in row.payments"
                            :key="pay.id"
                            class="payment-line"
                        >
                            <div>
                                <strong>{{ money(pay.amount) }}</strong
                                ><small
                                    >{{ date(pay.paid_on)
                                    }}<span v-if="pay.cancellation_reason">
                                        · {{ pay.cancellation_reason }}</span
                                    ></small
                                >
                            </div>
                            <StatusBadge :status="pay.status" /><a
                                v-if="
                                    ['admin', 'pic', 'client'].includes(
                                        user.role,
                                    )
                                "
                                class="text-link"
                                :href="`/proofs/${module === 'payments' ? 'client' : 'vendor'}/${pay.id}`"
                                >Bukti</a
                            >
                            <div v-if="manage" class="row-actions">
                                <button
                                    v-if="pay.status === 'menunggu'"
                                    class="button small"
                                    @click="
                                        act(
                                            module === 'payments'
                                                ? 'verify'
                                                : 'verify-vendor',
                                            pay.id,
                                        )
                                    "
                                >
                                    Verifikasi</button
                                ><button
                                    v-if="pay.status !== 'dibatalkan'"
                                    class="text-link"
                                    @click="
                                        act(
                                            module === 'payments'
                                                ? 'cancel-payment'
                                                : 'cancel-vendor-payment',
                                            pay.id,
                                        )
                                    "
                                >
                                    Batalkan
                                </button>
                            </div>
                        </div>
                        <button
                            v-if="
                                (manage || client) &&
                                project.status !== 'arsip' &&
                                (row.balance ?? 0) > 0
                            "
                            class="button secondary small"
                            @click="
                                act(
                                    module === 'payments'
                                        ? 'payment'
                                        : 'vendor-payment',
                                    row.id,
                                    { amount: row.balance },
                                )
                            "
                        >
                            <Plus :size="15" />{{
                                module === "payments"
                                    ? "Unggah bukti bayar"
                                    : "Catat pembayaran"
                            }}
                        </button></template
                    >
                    <template v-if="module === 'documents'"
                        ><div
                            v-for="v in row.versions"
                            :key="v.id"
                            class="document-line"
                        >
                            <FolderOpen :size="20" />
                            <div class="grow">
                                <strong>{{ v.original_name }}</strong
                                ><small
                                    >Versi {{ v.version }} ·
                                    {{ Math.ceil((v.size ?? 0) / 1024) }} KB ·
                                    {{
                                        date(
                                            v.created_at,
                                            project.timezone,
                                            true,
                                        )
                                    }}</small
                                >
                            </div>
                            <a
                                class="button secondary small"
                                :href="`/documents/versions/${v.id}/download`"
                                ><Download :size="15" />Unduh</a
                            >
                        </div>
                        <div class="inline-meta">
                            <span v-if="row.task_id"
                                >Lampiran pekerjaan #{{ row.task_id }}</span
                            ><span v-if="row.meeting_id"
                                >Lampiran meeting #{{ row.meeting_id }}</span
                            ><span v-if="row.project_vendor_id"
                                >Kontrak vendor #{{
                                    row.project_vendor_id
                                }}</span
                            >
                        </div>
                        <button
                            v-if="manage"
                            class="text-link"
                            @click="act('document-version', row.id)"
                        >
                            Unggah versi baru
                        </button></template
                    >
                    <template v-if="module === 'meetings'"
                        ><div class="inline-meta">
                            <span
                                ><CalendarDays :size="15" />{{
                                    date(row.starts_at, project.timezone, true)
                                }}</span
                            ><span>{{ row.participants }}</span>
                        </div>
                        <dl class="meeting-detail">
                            <dt>Agenda</dt>
                            <dd>{{ row.agenda }}</dd>
                            <dt>Pembahasan</dt>
                            <dd>{{ row.discussion || "Belum dicatat" }}</dd>
                            <dt>Keputusan</dt>
                            <dd>
                                {{ row.decisions || "Belum ada keputusan" }}
                            </dd>
                        </dl>
                        <div v-if="manage" class="row-actions">
                            <button
                                class="button secondary small"
                                @click="edit(row)"
                            >
                                Ubah catatan</button
                            ><button
                                class="button small"
                                @click="
                                    act('tasks', undefined, {
                                        meeting_id: row.id,
                                        kind: 'task',
                                        title: `Tindak lanjut ${row.title}`,
                                    })
                                "
                            >
                                Buat tindak lanjut</button
                            ><button
                                class="text-link"
                                @click="
                                    act('documents', undefined, {
                                        meeting_id: row.id,
                                        title: `Lampiran ${row.title}`,
                                    })
                                "
                            >
                                Tambah lampiran
                            </button>
                        </div></template
                    >
                    <template v-if="module === 'rundowns'"
                        ><div v-if="row.conflicts?.length" class="warning-box">
                            <strong>Peringatan jadwal bentrok</strong>
                            <p v-for="c in row.conflicts" :key="c">{{ c }}</p>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Urutan</th>
                                        <th>Waktu lokal</th>
                                        <th>Kegiatan</th>
                                        <th>Lokasi / jalur</th>
                                        <th>PIC / vendor</th>
                                        <th
                                            v-if="
                                                manage && row.status === 'draft'
                                            "
                                        >
                                            Atur
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, i) in row.items"
                                        :key="item.id"
                                    >
                                        <td>{{ i + 1 }}</td>
                                        <td>
                                            {{
                                                date(
                                                    item.starts_at,
                                                    project.timezone,
                                                    true,
                                                )
                                            }}<small
                                                >{{
                                                    item.duration
                                                }}
                                                menit</small
                                            >
                                        </td>
                                        <td>
                                            <strong>{{ item.title }}</strong
                                            ><small>{{ item.notes }}</small>
                                        </td>
                                        <td>
                                            {{ item.location
                                            }}<small>{{ item.track }}</small>
                                        </td>
                                        <td>
                                            {{
                                                options.members.find(
                                                    (m) =>
                                                        m.id ===
                                                        item.assignee_id,
                                                )?.name ?? "—"
                                            }}<small>{{
                                                options.project_vendors?.find(
                                                    (v) =>
                                                        v.id ===
                                                        item.project_vendor_id,
                                                )?.vendor?.name ??
                                                item.project_vendor?.vendor
                                                    ?.name
                                            }}</small>
                                        </td>
                                        <td
                                            v-if="
                                                manage && row.status === 'draft'
                                            "
                                        >
                                            <div class="row-actions">
                                                <button
                                                    class="text-link"
                                                    @click="
                                                        edit(
                                                            item,
                                                            'rundown-edit',
                                                        )
                                                    "
                                                >
                                                    Ubah</button
                                                ><button
                                                    class="text-link"
                                                    @click="
                                                        act(
                                                            'rundown-remove',
                                                            item.id,
                                                        )
                                                    "
                                                >
                                                    Hapus</button
                                                ><button
                                                    class="icon-button"
                                                    aria-label="Naikkan kegiatan"
                                                    :disabled="i === 0"
                                                    @click="
                                                        act(
                                                            'rundown-move',
                                                            item.id,
                                                            { direction: 'up' },
                                                        )
                                                    "
                                                >
                                                    <ArrowUp
                                                        :size="15"
                                                    /></button
                                                ><button
                                                    class="icon-button"
                                                    aria-label="Turunkan kegiatan"
                                                    :disabled="
                                                        i ===
                                                        (row.items?.length ??
                                                            0) -
                                                            1
                                                    "
                                                    @click="
                                                        act(
                                                            'rundown-move',
                                                            item.id,
                                                            {
                                                                direction:
                                                                    'down',
                                                            },
                                                        )
                                                    "
                                                >
                                                    <ArrowDown :size="15" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="!row.items?.length" class="empty-text">
                            Tambahkan kegiatan pertama untuk menyusun rundown.
                        </p>
                        <div class="row-actions">
                            <button
                                v-if="manage && row.status === 'draft'"
                                class="button secondary small"
                                @click="act('rundown-item', row.id)"
                            >
                                Tambah kegiatan</button
                            ><button
                                v-if="manage && row.status === 'draft'"
                                class="button small"
                                @click="act('finalize', row.id)"
                            >
                                Simpan final</button
                            ><button
                                v-if="
                                    manage &&
                                    row.status === 'final' &&
                                    row.visibility === 'client'
                                "
                                class="button small"
                                @click="startApproval(row)"
                            >
                                Minta persetujuan</button
                            ><button
                                v-if="manage"
                                class="text-link"
                                @click="act('revise-rundown', row.id)"
                            >
                                Buat versi draft baru</button
                            ><a
                                :href="`/rundowns/${row.id}/pdf`"
                                class="button secondary small"
                                ><Download :size="15" />PDF</a
                            >
                        </div></template
                    >
                </article>
                <div v-if="!records?.data.length" class="empty">
                    <Flower2 :size="35" />
                    <h2>Ruang untuk detail berikutnya</h2>
                    <p>
                        Belum ada data yang sesuai dengan pencarian atau izin
                        Anda.
                    </p>
                    <button
                        v-if="manage && operation"
                        class="button"
                        @click="create"
                    >
                        Tambah data pertama
                    </button>
                </div>
            </div>
            <Pagination
                v-if="records"
                :links="records.links"
                :total="records.total"
            />
            <div v-if="module === 'next-actions'" class="two-columns">
                <section class="panel">
                    <h2>Persetujuan menunggu</h2>
                    <Link
                        v-for="a in pendingApprovals"
                        :key="a.id"
                        :href="`${base}/approvals`"
                        class="task-line"
                        ><div class="grow">
                            <strong>{{ a.title }}</strong
                            ><small>{{ date(a.due_date) }}</small>
                        </div>
                        <ArrowUpRight :size="16"
                    /></Link>
                    <p v-if="!pendingApprovals?.length" class="empty-text">
                        Tidak ada keputusan tertunda.
                    </p>
                </section>
                <section class="panel">
                    <h2>Tagihan belum lunas</h2>
                    <Link
                        v-for="i in outstandingInvoices"
                        :key="i.id"
                        :href="`${base}/payments`"
                        class="task-line"
                        ><div class="grow">
                            <strong>{{ i.title }}</strong
                            ><small
                                >{{ date(i.due_date) }} ·
                                {{ money(i.balance) }}</small
                            >
                        </div>
                        <ArrowUpRight :size="16"
                    /></Link>
                    <p v-if="!outstandingInvoices?.length" class="empty-text">
                        Tidak ada saldo tagihan.
                    </p>
                </section>
            </div>
            <section
                v-if="module === 'vendors' && user.role === 'admin' && manage"
                class="panel"
            >
                <div class="panel-head">
                    <h2>Master vendor</h2>
                    <button
                        class="button secondary small"
                        @click="act('vendor-master')"
                    >
                        Tambah vendor
                    </button>
                </div>
                <div
                    v-for="v in options.vendors"
                    :key="v.id"
                    class="row-between table-line"
                >
                    <span
                        >{{ v.name }}
                        <small>{{ v.category }} · {{ v.contact }}</small></span
                    ><button
                        class="text-link"
                        @click="act('vendor-master', v.id, { ...v })"
                    >
                        Ubah master
                    </button>
                </div>
            </section>
            <section
                v-if="module === 'progress' && user.role === 'admin' && manage"
                class="panel"
            >
                <div class="panel-head">
                    <h2>Template checklist</h2>
                    <button class="button secondary small" @click="template()">
                        Template baru
                    </button>
                </div>
                <div
                    v-for="t in options.templates"
                    :key="t.id"
                    class="row-between table-line"
                >
                    <span
                        >{{ t.name }}
                        <small
                            >{{ t.items.length }} item · disalin saat
                            diterapkan</small
                        ></span
                    ><button class="text-link" @click="template(t)">
                        Ubah template
                    </button>
                </div>
            </section></template
        ><FormDialog :spec="spec" @close="spec = null"
    /></AppLayout>
</template>
