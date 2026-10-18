import type { Field, Options } from "./types";
const sel = (
    key: string,
    label: string,
    values: string[],
    required = true,
): Field => ({
    key,
    label,
    type: "select",
    required,
    options: values.map((v) => ({ value: v, label: v.replaceAll("_", " ") })),
});
const txt = (
    key: string,
    label: string,
    required = true,
    type: Field["type"] = "text",
): Field => ({ key, label, type, required });
const visibility = sel("visibility", "Visibilitas", ["internal", "client"]);
export function fields(op: string, o: Options): Field[] {
    const member: Field = {
        key: "assignee_id",
        label: "Penanggung jawab",
        type: "select",
        options: o.members.map((u) => ({ value: u.id, label: u.name })),
    };
    const vendor: Field = {
        key: "project_vendor_id",
        label: "Vendor proyek",
        type: "select",
        required: true,
        options: (o.project_vendors ?? []).map((v) => ({
            value: v.id,
            label: v.vendor?.name ?? "",
        })),
    };
    const file: Field = {
        key: "file",
        label: "Berkas (maks. 10 MB)",
        type: "file",
        required: true,
        hint: "PDF, JPG, PNG, WebP, DOCX, XLSX, atau TXT. Bukti pembayaran hanya PDF/gambar.",
    };
    const link = (
        key: string,
        label: string,
        list: { id: number; title?: string }[],
    ): Field => ({
        key,
        label,
        type: "select",
        options: list.map((x) => ({ value: x.id, label: x.title ?? "" })),
    });
    switch (op) {
        case "tasks":
            return [
                txt("title", "Judul pekerjaan"),
                txt("category", "Kategori"),
                sel("kind", "Jenis", ["checklist", "task"]),
                member,
                txt("due_date", "Target selesai", true, "date"),
                sel("priority", "Prioritas", ["rendah", "normal", "tinggi"]),
                sel("status", "Status", [
                    "belum_mulai",
                    "berjalan",
                    "terhambat",
                    "selesai",
                ]),
                {
                    key: "required",
                    label: "Wajib untuk progres",
                    type: "checkbox",
                },
                {
                    key: "applicable",
                    label: "Berlaku pada proyek",
                    type: "checkbox",
                },
                visibility,
                txt(
                    "blocker",
                    "Alasan hambatan (wajib jika terhambat)",
                    false,
                    "textarea",
                ),
                txt("notes", "Catatan", false, "textarea"),
                link("meeting_id", "Tindak lanjut meeting", o.meetings ?? []),
            ];
        case "task-status":
            return [
                sel("status", "Status", [
                    "belum_mulai",
                    "berjalan",
                    "terhambat",
                    "selesai",
                ]),
                txt("blocker", "Alasan hambatan", false, "textarea"),
            ];
        case "meetings":
            return [
                txt("title", "Judul meeting"),
                txt("starts_at", "Jadwal lokal proyek", true, "datetime-local"),
                txt("participants", "Peserta"),
                txt("agenda", "Agenda", true, "textarea"),
                txt("discussion", "Pembahasan", false, "textarea"),
                txt("decisions", "Keputusan", false, "textarea"),
                visibility,
            ];
        case "vendor-master":
            return [
                txt("name", "Nama usaha"),
                txt("category", "Kategori"),
                txt("contact", "Kontak"),
                txt("services", "Layanan", false, "textarea"),
                txt("internal_notes", "Catatan internal", false, "textarea"),
            ];
        case "vendors":
            return [
                {
                    key: "vendor_id",
                    label: "Vendor",
                    type: "select",
                    required: true,
                    options: (o.vendors ?? []).map((v) => ({
                        value: v.id,
                        label: v.name ?? "",
                    })),
                },
                txt("agreed_price", "Nilai kesepakatan (Rp)", true, "number"),
                sel("status", "Status booking", [
                    "penjajakan",
                    "dipesan",
                    "terkonfirmasi",
                    "dibatalkan",
                ]),
                txt(
                    "coordination",
                    "Koordinasi yang dibagikan",
                    false,
                    "textarea",
                ),
                txt("internal_notes", "Catatan internal", false, "textarea"),
            ];
        case "documents":
            return [
                txt("title", "Judul dokumen"),
                sel("category", "Kategori", [
                    "kontrak",
                    "konsep",
                    "invoice",
                    "referensi",
                    "rundown",
                    "lainnya",
                ]),
                visibility,
                file,
                link("task_id", "Lampiran pekerjaan", o.tasks ?? []),
                link("meeting_id", "Lampiran meeting", o.meetings ?? []),
                { ...vendor, required: false },
            ];
        case "document-version":
            return [file];
        case "approvals":
            return [
                txt("title", "Judul permintaan"),
                txt("material", "Materi versi ini", true, "textarea"),
                sel("rule", "Aturan pemberi keputusan", [
                    "salah_satu",
                    "keduanya",
                ]),
                txt("due_date", "Batas keputusan", true, "date"),
                {
                    key: "document_version_id",
                    label: "Versi dokumen yang dibagikan",
                    type: "select",
                    options: (o.documents ?? []).flatMap((doc) =>
                        (doc.versions ?? []).map((v) => ({
                            value: v.id,
                            label: `${doc.title} · v${v.version}`,
                        })),
                    ),
                },
                link("rundown_id", "Rundown final", o.rundowns ?? []),
            ];
        case "decide":
            return [
                sel("decision", "Keputusan", [
                    "disetujui",
                    "perlu_revisi",
                    "ditolak",
                ]),
                txt("comment", "Komentar keputusan", true, "textarea"),
            ];
        case "comment":
            return [txt("body", "Komentar", true, "textarea")];
        case "invoices":
            return [
                txt("title", "Nama termin"),
                txt("amount", "Nilai tagihan (Rp)", true, "number"),
                txt("due_date", "Jatuh tempo", true, "date"),
            ];
        case "obligations":
            return [
                vendor,
                txt("title", "Nama kewajiban"),
                txt("amount", "Nilai kewajiban (Rp)", true, "number"),
                txt("due_date", "Jatuh tempo", true, "date"),
            ];
        case "payment":
        case "vendor-payment":
            return [
                txt("amount", "Nilai pembayaran (Rp)", true, "number"),
                txt("paid_on", "Tanggal bayar", true, "date"),
                file,
            ];
        case "cancel-payment":
        case "cancel-vendor-payment":
            return [txt("reason", "Alasan pembatalan", true, "textarea")];
        case "rundowns":
            return [txt("title", "Judul rundown"), visibility];
        case "rundown-edit":
        case "rundown-item":
            return [
                txt("title", "Nama kegiatan"),
                txt(
                    "starts_at",
                    "Tanggal dan waktu mulai (lokal proyek)",
                    true,
                    "datetime-local",
                ),
                txt("duration", "Durasi (menit)", true, "number"),
                txt("location", "Lokasi"),
                txt("track", "Jalur paralel"),
                member,
                { ...vendor, required: false },
                txt("notes", "Catatan", false, "textarea"),
            ];
        case "apply-template":
            return [
                {
                    key: "template_id",
                    label: "Template checklist",
                    type: "select",
                    required: true,
                    options: (o.templates ?? []).map((t) => ({
                        value: t.id,
                        label: t.name,
                    })),
                },
            ];
        case "template":
            return [
                txt("name", "Nama template"),
                {
                    ...txt("template_lines", "Daftar item", true, "textarea"),
                    hint: "Satu baris per item: Judul | Kategori | internal atau client. Perubahan tidak mengubah proyek lama.",
                },
            ];
        default:
            return [];
    }
}
export const defaults: Record<string, unknown> = {
    kind: "task",
    category: "Persiapan",
    status: "belum_mulai",
    priority: "normal",
    required: false,
    applicable: true,
    visibility: "internal",
    rule: "salah_satu",
    decision: "disetujui",
    track: "utama",
    duration: 30,
};
