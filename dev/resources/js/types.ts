export interface User {
    id: number;
    name: string;
    email?: string;
    role: "admin" | "pic" | "team" | "client";
    active?: boolean;
}
export interface Project {
    id: number;
    name: string;
    couple_name: string;
    event_date: string;
    venue: string;
    timezone: string;
    status: string;
    members: User[];
    couple?: { bride_name: string; groom_name: string };
}
export interface Pagination<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    current_page: number;
    last_page: number;
}
export interface Entity {
    id: number;
    title?: string;
    name?: string;
    category?: string;
    contact?: string;
    services?: string;
    internal_notes?: string;
    coordination?: string;
    vendor_id?: number;
    vendor?: Entity;
    project_vendor?: Entity;
    agreed_price?: string;
    kind?: string;
    required?: boolean;
    applicable?: boolean;
    assignee_id?: number | null;
    assignee?: User;
    task_id?: number | null;
    meeting_id?: number | null;
    project_vendor_id?: number | null;
    status?: string;
    priority?: string;
    due_date?: string;
    notes?: string;
    blocker?: string;
    visibility?: string;
    starts_at?: string;
    participants?: string;
    agenda?: string;
    discussion?: string;
    decisions?: Decision[] | string;
    material?: string;
    rule?: string;
    version?: number;
    previous_id?: number | null;
    created_by?: number;
    document_version_id?: number | null;
    rundown_id?: number | null;
    recipients?: User[];
    comments?: { id: number; body: string; user: User; created_at: string }[];
    versions?: Entity[];
    original_name?: string;
    mime?: string;
    size?: number;
    amount?: string;
    paid?: number;
    balance?: number;
    payment_status?: string;
    payments?: Entity[];
    paid_on?: string;
    submitted_by?: number;
    verified_by?: number;
    cancellation_reason?: string;
    items?: Entity[];
    conflicts?: string[];
    duration?: number;
    position?: number;
    location?: string;
    track?: string;
    created_at?: string;
    read_at?: string | null;
    url?: string;
    items_template?: TemplateItem[];
}
export interface Decision {
    id: number;
    user: User;
    decision: string;
    comment: string;
    created_at: string;
}
export interface TemplateItem {
    title: string;
    category: string;
    visibility: string;
}
export interface Template {
    id: number;
    name: string;
    items: TemplateItem[];
}
export interface Options {
    members: User[];
    users?: User[];
    vendors?: Entity[];
    project_vendors?: Entity[];
    meetings?: Entity[];
    tasks?: Entity[];
    templates?: Template[];
    documents?: Entity[];
    rundowns?: Entity[];
}
export interface Summary {
    progress: number | null;
    tasks: {
        total: number;
        completed: number;
        blocked: number;
        overdue: number;
    };
    pending_approvals: number;
    approval_counts: Record<string, number>;
    invoices: Entity[];
    billed: number;
    paid: number;
    balance: number;
    upcoming: Entity[];
    vendors: Entity[];
    vendor_finance: Entity[];
    activities: {
        id: number;
        action: string;
        user: User;
        created_at: string;
    }[];
}
export interface Shared {
    [key: string]: unknown;
    auth: { user: User | null };
    projects: Project[];
    unread: number;
    flash: { success?: string };
}
export interface Field {
    key: string;
    label: string;
    type?:
        | "text"
        | "textarea"
        | "select"
        | "number"
        | "date"
        | "datetime-local"
        | "file"
        | "checkbox"
        | "multi"
        | "password"
        | "email";
    required?: boolean;
    options?: { value: string | number; label: string }[];
    hint?: string;
}
export interface FormSpec {
    title: string;
    url: string;
    method?: "post" | "put";
    fields: Field[];
    initial?: Record<string, unknown>;
    message?: string;
    submitLabel?: string;
}
