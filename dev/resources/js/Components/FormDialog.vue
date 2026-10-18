<script setup lang="ts">
import { ref, watch, nextTick } from "vue";
import { useForm } from "@inertiajs/vue3";
type FormValue =
    | string
    | number
    | boolean
    | null
    | File
    | (string | number)[]
    | { title: string; category: string; visibility: string }[];
import type { FormSpec } from "../types";
const props = defineProps<{ spec: FormSpec | null }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
const form = useForm<Record<string, FormValue>>({});
watch(
    () => props.spec,
    async (spec) => {
        if (!spec) {
            dialog.value?.close();
            return;
        }
        form.clearErrors();
        Object.keys(form.data()).forEach((k) => delete form[k]);
        const initial: Record<string, FormValue> = {};
        for (const f of spec.fields)
            initial[f.key] = (spec.initial?.[f.key] ??
                (f.type === "checkbox"
                    ? false
                    : f.type === "multi"
                      ? []
                      : "")) as FormValue;
        if (spec.initial?.direction)
            initial.direction = String(spec.initial.direction);
        Object.assign(form, initial);
        form.defaults(initial);
        await nextTick();
        dialog.value?.showModal();
    },
);
function file(event: Event, key: string) {
    form[key] = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function toggle(key: string, id: string | number) {
    const current = (form[key] ?? []) as (string | number)[];
    form[key] = current.includes(id)
        ? current.filter((x) => x !== id)
        : [...current, id];
}
function submit() {
    if (!props.spec) return;
    const spec = props.spec;
    form.transform((data) => {
        const copy = { ...data };
        if (copy.template_lines) {
            copy.items = String(copy.template_lines)
                .split("\n")
                .filter(Boolean)
                .map((line) => {
                    const [title, category, visibility] = line
                        .split("|")
                        .map((s) => s.trim());
                    return {
                        title,
                        category: category || "Persiapan",
                        visibility: visibility || "client",
                    };
                });
            delete copy.template_lines;
        }
        return copy;
    });
    form.submit(spec.method ?? "post", spec.url, {
        preserveScroll: true,
        onSuccess: () => emit("close"),
    });
}
</script>
<template>
    <dialog
        ref="dialog"
        class="form-dialog"
        @cancel.prevent="!form.processing && emit('close')"
    >
        <form v-if="spec" @submit.prevent="submit">
            <div class="dialog-head">
                <div>
                    <span class="eyebrow">WEO WORKSPACE</span>
                    <h2>{{ spec.title }}</h2>
                </div>
                <button
                    type="button"
                    class="icon-button"
                    aria-label="Tutup formulir"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    ×
                </button>
            </div>
            <p v-if="spec.message" class="muted">{{ spec.message }}</p>
            <div class="form-fields">
                <div
                    v-for="field in spec.fields"
                    :key="field.key"
                    class="field"
                    :class="{
                        full:
                            field.type === 'textarea' || field.type === 'multi',
                    }"
                >
                    <label :for="`f-${field.key}`"
                        >{{ field.label
                        }}<span v-if="field.required" aria-hidden="true">
                            *</span
                        ></label
                    ><textarea
                        v-if="field.type === 'textarea'"
                        :id="`f-${field.key}`"
                        v-model="form[field.key] as string"
                        :required="field.required"
                        rows="4"
                    /><select
                        v-else-if="field.type === 'select'"
                        :id="`f-${field.key}`"
                        v-model="form[field.key]"
                        :required="field.required"
                    >
                        <option value="">Pilih…</option>
                        <option
                            v-for="o in field.options"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.label }}
                        </option></select
                    ><input
                        v-else-if="field.type === 'file'"
                        :id="`f-${field.key}`"
                        type="file"
                        :required="field.required"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.txt"
                        @change="file($event, field.key)"
                    /><input
                        v-else-if="field.type === 'checkbox'"
                        :id="`f-${field.key}`"
                        v-model="form[field.key]"
                        type="checkbox"
                    />
                    <div v-else-if="field.type === 'multi'" class="check-grid">
                        <label v-for="o in field.options" :key="o.value"
                            ><input
                                type="checkbox"
                                :checked="
                                    (
                                        (form[field.key] ?? []) as (
                                            string | number
                                        )[]
                                    ).includes(o.value)
                                "
                                @change="toggle(field.key, o.value)"
                            />{{ o.label }}</label
                        >
                    </div>
                    <input
                        v-else
                        :id="`f-${field.key}`"
                        v-model="form[field.key] as string"
                        :type="field.type ?? 'text'"
                        :required="field.required"
                        :min="field.type === 'number' ? 0 : undefined"
                        :step="field.type === 'number' ? 1 : undefined"
                    /><small v-if="field.hint" class="muted">{{
                        field.hint
                    }}</small
                    ><small v-if="form.errors[field.key]" class="error">{{
                        form.errors[field.key]
                    }}</small>
                </div>
            </div>
            <div
                v-if="Object.keys(form.errors).length"
                class="error-box"
                role="alert"
            >
                <div v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </div>
            </div>
            <div class="dialog-footer">
                <button
                    type="button"
                    class="button secondary"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    Batal</button
                ><button class="button" :disabled="form.processing">
                    {{
                        form.processing
                            ? "Menyimpan…"
                            : (spec.submitLabel ?? "Simpan")
                    }}
                </button>
            </div>
        </form>
    </dialog>
</template>
