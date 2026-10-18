<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import {
    Plus,
    ArrowUpRight,
    MapPin,
    CalendarDays,
    Search,
    Flower2,
} from "lucide-vue-next";
import AppLayout from "../Layouts/AppLayout.vue";
import FormDialog from "../Components/FormDialog.vue";
import StatusBadge from "../Components/StatusBadge.vue";
import Pagination from "../Components/Pagination.vue";
import type {
    Project,
    User,
    Pagination as Pager,
    Template,
    FormSpec,
    Field,
    Shared,
} from "../types";
import { date } from "../format";
const props = defineProps<{
    records: Pager<Project>;
    users: User[];
    templates: Template[];
    filters: { q?: string };
}>();
const admin = usePage<Shared>().props.auth.user?.role === "admin";
const spec = ref<FormSpec | null>(null);
const q = ref(props.filters.q ?? "");
function edit(p?: Project) {
    const fields: Field[] = [
        { key: "name", label: "Nama proyek", required: true },
        {
            key: "couple_name",
            label: "Nama pasangan ditampilkan",
            required: true,
        },
        {
            key: "bride_name",
            label: "Nama calon pengantin perempuan",
            required: true,
        },
        {
            key: "groom_name",
            label: "Nama calon pengantin laki-laki",
            required: true,
        },
        {
            key: "event_date",
            label: "Tanggal acara",
            type: "date",
            required: true,
        },
        { key: "venue", label: "Lokasi acara", required: true },
        {
            key: "timezone",
            label: "Zona waktu",
            type: "select",
            required: true,
            options: ["Asia/Makassar", "Asia/Jakarta", "Asia/Jayapura"].map(
                (v) => ({ value: v, label: v }),
            ),
        },
        {
            key: "status",
            label: "Status proyek",
            type: "select",
            required: true,
            options: ["persiapan", "berjalan", "selesai", "arsip"].map((v) => ({
                value: v,
                label: v,
            })),
        },
        {
            key: "member_ids",
            label: "Anggota proyek (PIC, tim, pasangan)",
            type: "multi",
            options: props.users.map((u) => ({
                value: u.id,
                label: `${u.name} · ${u.role}`,
            })),
        },
    ];
    if (!p)
        fields.push({
            key: "template_id",
            label: "Salin template checklist",
            type: "select",
            options: props.templates.map((t) => ({
                value: t.id,
                label: t.name,
            })),
        });
    spec.value = {
        title: p ? "Ubah proyek & penugasan" : "Buat proyek pernikahan",
        url: p ? `/projects/${p.id}` : "/projects",
        method: p ? "put" : "post",
        fields,
        initial: p
            ? {
                  ...p,
                  ...p.couple,
                  event_date: p.event_date.slice(0, 10),
                  member_ids: p.members.map((m) => m.id),
              }
            : { timezone: "Asia/Makassar", status: "persiapan" },
        message: p
            ? "Status arsip mempertahankan histori dan menghentikan perubahan operasional."
            : undefined,
    };
}
</script>
<template>
    <Head title="Proyek pernikahan" /><AppLayout
        ><div class="page-heading">
            <div>
                <span class="eyebrow">CERITA YANG KITA RENCANAKAN</span>
                <h1>Proyek pernikahan</h1>
                <p class="muted">
                    Atur proyek, pasangan, dan orang-orang di balik setiap
                    detailnya.
                </p>
            </div>
            <button v-if="admin" class="button" @click="edit()">
                <Plus :size="17" />Proyek baru
            </button>
        </div>
        <form
            class="filter-bar"
            @submit.prevent="
                router.get('/projects', { q }, { preserveState: true })
            "
        >
            <Search :size="18" /><input
                v-model="q"
                aria-label="Cari proyek"
                placeholder="Cari nama proyek…"
            /><button class="button secondary">Cari</button>
        </form>
        <div class="project-list">
            <article v-for="p in records.data" :key="p.id" class="project-row">
                <span class="project-monogram"><Flower2 :size="27" /></span>
                <div class="grow">
                    <h2>
                        <Link :href="`/projects/${p.id}/overview`">{{
                            p.couple_name
                        }}</Link>
                    </h2>
                    <p class="muted">{{ p.name }}</p>
                    <div class="inline-meta">
                        <span
                            ><CalendarDays :size="14" />{{
                                date(p.event_date)
                            }}</span
                        ><span><MapPin :size="14" />{{ p.venue }}</span
                        ><span>{{
                            p.members
                                .filter((m) => m.role === "pic")
                                .map((m) => m.name)
                                .join(", ") || "PIC belum ditugaskan"
                        }}</span>
                    </div>
                </div>
                <StatusBadge :status="p.status" />
                <div class="row-actions">
                    <button
                        v-if="admin"
                        class="button secondary small"
                        @click="edit(p)"
                    >
                        Ubah</button
                    ><Link
                        class="button small"
                        :href="`/projects/${p.id}/overview`"
                        >Buka <ArrowUpRight :size="15"
                    /></Link>
                </div>
            </article>
            <div v-if="!records.data.length" class="empty">
                <h2>Belum ada proyek</h2>
                <p>Proyek yang dapat Anda akses akan muncul di sini.</p>
            </div>
        </div>
        <Pagination :links="records.links" :total="records.total" /><FormDialog
            :spec="spec"
            @close="spec = null"
    /></AppLayout>
</template>
