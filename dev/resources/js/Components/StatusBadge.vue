<script setup lang="ts">
import { label } from "../format";
defineProps<{ status?: string }>();
</script>
<template>
    <span class="badge" :class="status"
        ><span aria-hidden="true">{{
            [
                "selesai",
                "disetujui",
                "lunas",
                "terverifikasi",
                "final",
                "terkonfirmasi",
            ].includes(status ?? "")
                ? "✓"
                : ["terhambat", "terlambat", "ditolak"].includes(status ?? "")
                  ? "!"
                  : "◦"
        }}</span>
        {{ label(status) || "Belum mulai" }}</span
    >
</template>
