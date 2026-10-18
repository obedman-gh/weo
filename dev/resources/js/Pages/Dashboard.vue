<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import {
    ArrowUpRight,
    CalendarDays,
    MapPin,
    Plus,
    Heart,
    Clock3,
    CircleCheck,
    Flower2,
} from "lucide-vue-next";
import AppLayout from "../Layouts/AppLayout.vue";
import StatusBadge from "../Components/StatusBadge.vue";
import { date, daysUntil } from "../format";
import type { Project, Summary, Shared } from "../types";
const props = defineProps<{
    cards: { project: Project; summary: Summary }[];
    today: string;
}>();
const user = usePage<Shared>().props.auth.user!;
const stats = computed(() => [
    {
        label: "Proyek aktif",
        value: props.cards.filter(
            (c) => !["arsip", "selesai"].includes(c.project.status),
        ).length,
        icon: Heart,
        note: "Cerita yang sedang direncanakan",
    },
    {
        label: "Perlu ditindaklanjuti",
        value: props.cards.reduce((n, c) => n + c.summary.tasks.overdue, 0),
        icon: Clock3,
        note: "Pekerjaan melewati tenggat",
    },
    {
        label: "Menunggu persetujuan",
        value: props.cards.reduce((n, c) => n + c.summary.pending_approvals, 0),
        icon: CircleCheck,
        note: "Keputusan untuk langkah berikutnya",
    },
]);
</script>
<template>
    <Head title="Dashboard" /><AppLayout
        ><div class="page-heading">
            <div>
                <span class="eyebrow"
                    >{{ date(today) }} · RUANG KERJA ANDA</span
                >
                <h1>
                    Selamat datang, {{ user.name.split(" ")[0]
                    }}<span class="rose">.</span>
                </h1>
                <p class="muted">
                    Mari siapkan setiap detail untuk cerita yang tak terlupakan.
                </p>
            </div>
            <Link href="/projects" class="button"
                ><Plus :size="17" />{{
                    user.role === "admin" ? "Kelola proyek" : "Buka proyek"
                }}</Link
            >
        </div>
        <section class="stats-grid">
            <article v-for="s in stats" :key="s.label" class="stat-card">
                <div>
                    <span>{{ s.label }}</span
                    ><component :is="s.icon" :size="19" />
                </div>
                <strong>{{ s.value }}</strong
                ><small>{{ s.note }}</small>
            </article>
        </section>
        <section class="section-head">
            <div>
                <span class="eyebrow">SETIAP PASANGAN, CERITA BERBEDA</span>
                <h2>Pernikahan dalam persiapan</h2>
            </div>
            <Link href="/projects" class="text-link"
                >Semua proyek <ArrowUpRight :size="16"
            /></Link>
        </section>
        <section class="project-grid">
            <Link
                v-for="(c, i) in cards"
                :key="c.project.id"
                :href="`/projects/${c.project.id}/overview`"
                class="project-card"
                ><div class="project-cover" :class="`cover-${i % 3}`">
                    <Flower2 class="cover-flower" :size="140" /><span
                        class="cover-index"
                        >{{ String(i + 1).padStart(2, "0") }} / THE
                        WEDDING</span
                    ><span class="countdown">{{
                        daysUntil(c.project.event_date, c.project.timezone) >= 0
                            ? `H − ${daysUntil(c.project.event_date, c.project.timezone)}`
                            : "Acara telah lewat"
                    }}</span>
                    <h2>{{ c.project.couple_name }}</h2>
                    <span class="cover-caption">A BEAUTIFUL BEGINNING</span>
                </div>
                <div class="project-body">
                    <div class="row-between">
                        <StatusBadge :status="c.project.status" /><ArrowUpRight
                            :size="19"
                        />
                    </div>
                    <p>
                        <CalendarDays :size="15" />{{
                            date(c.project.event_date)
                        }}<span class="dot">·</span><MapPin :size="15" />{{
                            c.project.venue
                        }}
                    </p>
                    <div class="row-between progress-label">
                        <span>Progres persiapan</span
                        ><strong>{{
                            c.summary.progress === null
                                ? "Belum ada checklist"
                                : `${c.summary.progress}%`
                        }}</strong>
                    </div>
                    <div class="progress-track">
                        <div
                            :style="{ width: `${c.summary.progress ?? 0}%` }"
                        />
                    </div>
                    <div class="project-foot">
                        <span>{{
                            c.project.members
                                .filter((m) => m.role === "pic")
                                .map((m) => m.name)
                                .join(", ") || "PIC belum ditugaskan"
                        }}</span
                        ><span
                            v-if="c.summary.tasks.overdue"
                            class="warning-text"
                            >{{ c.summary.tasks.overdue }} terlambat</span
                        ><span v-else>Persiapan terpantau</span>
                    </div>
                </div></Link
            >
            <div v-if="!cards.length" class="empty">
                <Flower2 />
                <h3>Mulai cerita pertama</h3>
                <p>Buat proyek dan tentukan anggota untuk memulai.</p>
                <Link href="/projects" class="button">Buka proyek</Link>
            </div>
        </section>
        <aside class="quote-strip">
            <Flower2 :size="28" />
            <p>
                “Pernikahan yang indah dimulai dari persiapan yang penuh
                perhatian.”
            </p>
            <span>WITH LOVE, WEO</span>
        </aside></AppLayout
    >
</template>
