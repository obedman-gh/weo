<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { Flower2, ArrowUpRight } from "lucide-vue-next";
</script>
<template>
    <Head title="Wedding Organizer Workspace" />
    <main class="welcome">
        <nav>
            <span class="brand"><Flower2 /><span>weo.</span></span
            ><Link href="/login" class="button secondary"
                >Masuk workspace <ArrowUpRight :size="17"
            /></Link>
        </nav>
        <section>
            <span class="eyebrow">WEDDING ORGANIZER WORKSPACE</span>
            <h1>
                Satu ruang.<br />Ribuan detail.<br /><em
                    >Satu hari istimewa.</em
                >
            </h1>
            <p>
                Rencanakan persiapan, satukan keputusan, dan jalani<br />perjalanan
                menuju hari bahagia dengan lebih tenang.
            </p>
            <Link href="/login" class="button"
                >Buka ruang pernikahan <ArrowUpRight :size="18"
            /></Link>
        </section>
        <div class="welcome-seal">
            <Flower2 :size="80" /><span>PLANNED WITH LOVE</span>
        </div>
        <footer>
            Untuk tim yang merencanakan. Untuk pasangan yang merayakan.
        </footer>
    </main>
</template>
