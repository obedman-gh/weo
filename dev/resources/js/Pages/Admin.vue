<script setup lang="ts">
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import FormDialog from "../Components/FormDialog.vue";
import Pagination from "../Components/Pagination.vue";
import type { User, Pagination as Pager, FormSpec } from "../types";
import { date } from "../format";
defineProps<{
    users: Pager<User>;
    logs: Pager<{
        id: number;
        action: string;
        subject_type: string;
        subject_id: number;
        user: User;
        created_at: string;
    }>;
}>();
const spec = ref<FormSpec | null>(null);
function edit(u?: User) {
    spec.value = {
        title: u ? "Ubah akun" : "Buat akun terkontrol",
        url: u ? `/admin/users/${u.id}` : "/admin/users",
        method: u ? "put" : "post",
        initial: u
            ? { ...u, active: Boolean(u.active) }
            : { role: "client", active: true },
        fields: [
            { key: "name", label: "Nama", required: true },
            { key: "email", label: "Email", type: "email", required: true },
            {
                key: "password",
                label: u
                    ? "Kata sandi baru (kosongkan jika tetap)"
                    : "Kata sandi (minimal 12 karakter)",
                type: "password",
                required: !u,
            },
            {
                key: "role",
                label: "Role",
                type: "select",
                required: true,
                options: ["admin", "pic", "team", "client"].map((v) => ({
                    value: v,
                    label: v,
                })),
            },
            { key: "active", label: "Akun aktif", type: "checkbox" },
        ],
    };
}
</script>
<template>
    <Head title="Pengguna & log" /><AppLayout
        ><div class="page-heading">
            <div>
                <span class="eyebrow">ADMINISTRASI</span>
                <h1>Pengguna & aktivitas</h1>
                <p class="muted">
                    Kelola akses akun dan telusuri perubahan penting.
                </p>
            </div>
            <button class="button" @click="edit()">Buat akun</button>
        </div>
        <section class="panel">
            <h2>Akun pengguna</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users.data" :key="u.id">
                            <td>{{ u.name }}</td>
                            <td>{{ u.email }}</td>
                            <td>{{ u.role }}</td>
                            <td>{{ u.active ? "Aktif" : "Nonaktif" }}</td>
                            <td>
                                <button class="text-link" @click="edit(u)">
                                    Ubah akun
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="users.links" :total="users.total" />
        </section>
        <section class="panel">
            <h2>Activity log</h2>
            <div v-for="l in logs.data" :key="l.id" class="activity-line">
                <span class="activity-dot" />
                <div>
                    <strong>{{ l.action }}</strong
                    ><small
                        >{{ l.user.name }} · {{ l.subject_type }} #{{
                            l.subject_id
                        }}
                        · {{ date(l.created_at, "Asia/Makassar", true) }}</small
                    >
                </div>
            </div>
            <Pagination :links="logs.links" :total="logs.total" />
        </section>
        <FormDialog :spec="spec" @close="spec = null"
    /></AppLayout>
</template>
