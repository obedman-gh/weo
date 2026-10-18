<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import {
    LayoutDashboard,
    Heart,
    ListChecks,
    CircleCheck,
    ArrowUpRight,
    Handshake,
    Wallet,
    FolderOpen,
    MessageSquare,
    CalendarDays,
    Bell,
    ChartNoAxesCombined,
    Users,
    Menu,
    LogOut,
    ChevronDown,
    Flower2,
} from "lucide-vue-next";
import type { Shared, Project } from "../types";
const props = defineProps<{ project?: Project; module?: string }>();
const page = usePage<Shared>();
const open = ref(false);
const user = computed(() => page.props.auth.user!);
const selected = computed(() => props.project?.id ?? "");
const nav = [
    ["overview", "Ringkasan", LayoutDashboard],
    ["progress", "Wedding Progress", ListChecks],
    ["approvals", "Approval Center", CircleCheck],
    ["next-actions", "Next Action", ArrowUpRight],
    ["tasks", "Tugas tim", Heart],
    ["vendors", "Vendor", Handshake],
    ["payments", "Pembayaran klien", Wallet],
    ["vendor-payments", "Pembayaran vendor", Wallet],
    ["documents", "Dokumen", FolderOpen],
    ["meetings", "Meeting Notes", MessageSquare],
    ["rundowns", "Rundown", CalendarDays],
    ["reports", "Laporan", ChartNoAxesCombined],
] as const;
const visibleNav = computed(() =>
    nav.filter(
        ([key]) =>
            key !== "vendor-payments" ||
            ["admin", "pic"].includes(user.value.role),
    ),
);
function switchProject(event: Event) {
    const id = (event.target as HTMLSelectElement).value;
    if (id) router.visit(`/projects/${id}/${props.module ?? "overview"}`);
}
</script>
<template>
    <div class="app">
        <aside class="sidebar" :class="{ open }">
            <Link href="/dashboard" class="brand"
                ><Flower2 :size="30" /><span
                    >weo<span class="brand-dot">.</span
                    ><small>WEDDING WORKSPACE</small></span
                ></Link
            >
            <p class="sidebar-label">RUANG KERJA</p>
            <Link
                href="/dashboard"
                class="nav-link"
                :class="{ active: page.url === '/dashboard' }"
                ><LayoutDashboard :size="18" />Dashboard</Link
            ><Link
                href="/projects"
                class="nav-link"
                :class="{ active: page.url === '/projects' }"
                ><Heart :size="18" />Proyek pernikahan</Link
            ><template v-if="project"
                ><p class="sidebar-label">PROYEK PILIHAN</p>
                <Link
                    v-for="[key, title, icon] in visibleNav"
                    :key="key"
                    :href="`/projects/${project.id}/${key}`"
                    class="nav-link"
                    :class="{ active: module === key }"
                    @click="open = false"
                    ><component :is="icon" :size="18" />{{ title }}</Link
                ></template
            >
            <p class="sidebar-label">PERSONAL</p>
            <Link href="/notifications" class="nav-link"
                ><Bell :size="18" />Notifikasi<span
                    v-if="page.props.unread"
                    class="nav-count"
                    >{{ page.props.unread }}</span
                ></Link
            ><Link v-if="user.role === 'admin'" href="/admin" class="nav-link"
                ><Users :size="18" />Pengguna & log</Link
            >
            <div class="sidebar-note">
                <Flower2 :size="22" />
                <p>Setiap detail kecil,<br />untuk hari yang berarti.</p>
                <small>TERENCANA DENGAN HATI</small>
            </div>
            <button class="nav-link logout" @click="router.post('/logout')">
                <LogOut :size="17" />Keluar
            </button>
        </aside>
        <button
            v-if="open"
            class="sidebar-overlay"
            aria-label="Tutup navigasi"
            @click="open = false"
        />
        <div class="main">
            <header class="topbar">
                <div class="top-left">
                    <button
                        class="icon-button mobile-menu"
                        aria-label="Buka navigasi"
                        @click="open = !open"
                    >
                        <Menu :size="22" /></button
                    ><span class="top-crumb"
                        >Workspace <span>/</span>
                        <strong>{{
                            project?.couple_name ?? "Semua proyek"
                        }}</strong></span
                    >
                </div>
                <div class="top-actions">
                    <label class="project-switch"
                        ><Heart :size="15" /><select
                            aria-label="Pilih proyek"
                            :value="selected"
                            @change="switchProject"
                        >
                            <option value="">Pilih proyek</option>
                            <option
                                v-for="p in page.props.projects"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.couple_name }}
                            </option></select
                        ><ChevronDown :size="14" /></label
                    ><Link
                        href="/notifications"
                        class="notification-link"
                        aria-label="Notifikasi"
                        ><Bell :size="20" /><span
                            v-if="page.props.unread"
                            class="unread-dot" /></Link
                    ><span class="top-divider" />
                    <div class="profile">
                        <span class="avatar">{{
                            user.name
                                .split(" ")
                                .map((s) => s[0])
                                .slice(0, 2)
                                .join("")
                        }}</span
                        ><span
                            ><strong>{{ user.name }}</strong
                            ><small>{{
                                {
                                    admin: "Owner / Admin",
                                    pic: "Wedding Planner",
                                    team: "Tim WO",
                                    client: "Portal pasangan",
                                }[user.role]
                            }}</small></span
                        >
                    </div>
                </div>
            </header>
            <main id="main" class="content">
                <div
                    v-if="page.props.flash.success"
                    class="flash"
                    role="status"
                >
                    ✓ {{ page.props.flash.success }}
                </div>
                <slot />
            </main>
            <footer class="page-footer">
                <span>WEO · Wedding Organizer Workspace</span
                ><span>Dibuat untuk momen yang berarti.</span>
            </footer>
        </div>
    </div>
</template>
