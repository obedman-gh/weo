<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}>();
const clean = (s: string) =>
    s.includes("Previous")
        ? "Sebelumnya"
        : s.includes("Next")
          ? "Berikutnya"
          : s.replace(/&[^;]+;/g, "");
</script>
<template>
    <div class="pagination">
        <small class="muted">{{ total }} data</small>
        <nav aria-label="Halaman data">
            <template v-for="(link, i) in links" :key="i"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    :class="{ active: link.active }"
                    preserve-scroll
                    >{{ clean(link.label) }}</Link
                ><span v-else>{{ clean(link.label) }}</span></template
            >
        </nav>
    </div>
</template>
