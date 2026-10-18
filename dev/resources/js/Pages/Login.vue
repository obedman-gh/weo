<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Flower2, ArrowRight } from "lucide-vue-next";
const form = useForm({ email: "", password: "" });
</script>
<template>
    <Head title="Masuk" />
    <main class="login-page">
        <section class="login-story">
            <Link href="/" class="brand"
                ><Flower2 :size="34" /><span
                    >weo.<small>WEDDING WORKSPACE</small></span
                ></Link
            >
            <div>
                <span class="eyebrow">DI BALIK HARI YANG SEMPURNA</span>
                <h1>
                    Setiap cerita cinta,<br />layak direncanakan<br /><em
                        >dengan hati.</em
                    >
                </h1>
                <p>
                    Satu ruang untuk menyatukan setiap detail,<br />setiap
                    keputusan, dan semua orang yang berperan.
                </p>
            </div>
            <small>TERENCANA BAIK. DIRAYAKAN SEPENUH HATI.</small>
            <div class="botanical">✳</div>
        </section>
        <section class="login-form">
            <div>
                <span class="eyebrow">SELAMAT DATANG KEMBALI</span>
                <h2>Rencana indah dimulai di sini.</h2>
                <p class="muted">Masuk ke ruang kerja pernikahan Anda.</p>
                <form @submit.prevent="form.post('/login')">
                    <div class="field">
                        <label for="email">Alamat email</label
                        ><input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            placeholder="nama@contoh.test"
                        /><small class="error">{{ form.errors.email }}</small>
                    </div>
                    <div class="field">
                        <label for="password">Kata sandi</label
                        ><input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                        /><small class="error">{{
                            form.errors.password
                        }}</small>
                    </div>
                    <button class="button" :disabled="form.processing">
                        {{
                            form.processing
                                ? "Memproses…"
                                : "Masuk ke workspace"
                        }}<ArrowRight :size="18" />
                    </button>
                </form>
                <p class="login-help">
                    Akses akun dikelola oleh Wedding Organizer Anda.<br />Hubungi
                    admin jika memerlukan bantuan.
                </p>
            </div>
        </section>
    </main>
</template>
