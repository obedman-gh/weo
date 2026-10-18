<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
defineProps<{ status: number }>();
const messages: Record<number, string> = {
    403: "Anda tidak memiliki izin untuk membuka informasi ini.",
    404: "Halaman atau data tidak ditemukan.",
    419: "Sesi telah berakhir. Muat ulang halaman dan masuk kembali.",
    422: "Tindakan tidak dapat diproses pada kondisi data saat ini.",
    429: "Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.",
    500: "Terjadi kendala saat memproses data. Silakan coba kembali.",
    503: "Workspace sedang dalam pemeliharaan.",
};
</script>
<template>
    <Head :title="`Kendala ${status}`" />
    <main class="empty" style="max-width: 650px; margin: 15vh auto">
        <span class="eyebrow">WEO · {{ status }}</span>
        <h1 style="margin: 20px">Ada detail yang perlu diperiksa.</h1>
        <p>{{ messages[status] ?? "Permintaan tidak dapat diproses." }}</p>
        <Link class="button" href="/dashboard">Kembali ke workspace</Link>
    </main>
</template>
