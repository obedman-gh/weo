<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { Bell, ArrowUpRight } from "lucide-vue-next";
import AppLayout from "../Layouts/AppLayout.vue";
import Pagination from "../Components/Pagination.vue";
import type { Entity, Pagination as Pager } from "../types";
import { date } from "../format";
defineProps<{ records: Pager<Entity> }>();
</script>
<template>
    <Head title="Notifikasi" /><AppLayout
        ><div class="page-heading">
            <div>
                <span class="eyebrow">TETAP TERHUBUNG</span>
                <h1>Notifikasi</h1>
                <p class="muted">Perubahan dan pengingat dari proyek Anda.</p>
            </div>
            <Bell :size="28" />
        </div>
        <div class="panel">
            <article
                v-for="n in records.data"
                :key="n.id"
                class="notification-row"
                :class="{ unread: !n.read_at }"
            >
                <span class="activity-dot" />
                <div class="grow">
                    <h3>{{ n.title }}</h3>
                    <small
                        >{{ date(n.created_at, "Asia/Makassar", true) }} ·
                        {{ n.read_at ? "Sudah dibaca" : "Belum dibaca" }}</small
                    >
                </div>
                <button
                    v-if="!n.read_at"
                    class="text-link"
                    @click="
                        router.post(
                            `/notifications/${n.id}/read`,
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    Tandai dibaca</button
                ><Link
                    :href="n.url ?? '/dashboard'"
                    class="icon-button"
                    aria-label="Buka sumber notifikasi"
                    ><ArrowUpRight :size="19"
                /></Link>
            </article>
            <p v-if="!records.data.length" class="empty-text">
                Belum ada notifikasi.
            </p>
        </div>
        <Pagination :links="records.links" :total="records.total"
    /></AppLayout>
</template>
