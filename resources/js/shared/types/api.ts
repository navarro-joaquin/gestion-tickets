export interface Category {
    slug: string;
    name: string;
    is_preferential: boolean;
}

export interface TicketCode {
    code: string;
    categories: Category[];
}

export interface ServiceSummary {
    id: number;
    slug: string;
    name: string;
    label: string;
    description: string | null;
    available_from: string | null;
    available_to: string | null;
    available_days: number[] | null;
}

export interface Section {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    matrix_confirmed_at: string | null;
    codes: TicketCode[];
    services: ServiceSummary[];
}

export interface Station {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
    section?: {
        slug: string;
        name: string;
    };
}

export interface Operator {
    id: number;
    name: string;
    role: string;
    role_label: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    default_section: {
        slug: string;
        name: string;
    } | null;
    default_station: {
        id: number;
        name: string;
    } | null;
}

export interface TransferHistory {
    id: number;
    from_section?: {
        slug: string;
        name: string;
    };
    to_section?: {
        slug: string;
        name: string;
    };
    from_operator?: {
        id: number;
        name: string;
    } | null;
    to_operator?: {
        id: number;
        name: string;
    } | null;
    reason: string | null;
    transferred_at: string;
}

export interface PrioritySnapshot {
    profile_name?: string;
    selected_group?: string;
    reason?: string;
    waited_minutes?: number;
    score?: number;
    ratios?: {
        general: number;
        preferential: number;
    };
}

export interface Ticket {
    id: number;
    code: string;
    number: number;
    display_number: string;
    label: string;
    status: string;
    status_label: string;
    is_transferred: boolean;
    is_preferential: boolean;
    service_label: string | null;
    station_label: string | null;
    call_count: number;
    priority_score: number;
    priority_snapshot: PrioritySnapshot | null;
    issued_at: string;
    called_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    closed_at: string | null;
    section?: {
        slug: string;
        name: string;
    };
    current_section?: {
        slug: string;
        name: string;
    } | null;
    transfers?: TransferHistory[];
    category?: Category;
    service?: {
        id: number;
        slug: string;
        name: string;
        label: string;
    } | null;
}

export interface IssuedTicketResponse {
    data: Ticket;
    print: PrintPayload;
}

export interface PrintPayload {
    escpos_base64: string;
    text: string;
}

export interface OperatorSession {
    id: number;
    operator?: {
        id: number;
        name: string;
    };
    section?: {
        slug: string;
        name: string;
    };
    station: {
        id: number;
        name: string;
    } | null;
    started_at: string;
    ended_at: string | null;
    is_active: boolean;
    history?: Array<{
        station: string;
        assigned_at: string;
        released_at: string | null;
    }>;
}

export interface ConsoleQueue {
    section: {
        slug: string;
        name: string;
    };
    waiting_count: number;
    active_ticket: Ticket | null;
    session: OperatorSession | null;
    stations: Station[];
}

export interface DisplaySnapshot {
    data: Array<{
        section: {
            slug: string;
            name: string;
        };
        waiting_count: number;
        calling: Ticket[];
    }>;
    display: DisplaySummary | null;
    playlists: Playlist[];
    updated_at: string;
}

export type DisplayLayout = 'queue' | 'mixed' | 'media';

export interface DisplaySummary {
    id: number;
    name: string;
    slug: string;
    layout: DisplayLayout;
}

export interface MediaAsset {
    id: number;
    name: string;
    type: 'image' | 'video';
    mime: string | null;
    file_size: number;
    duration_seconds: number | null;
    is_active: boolean;
    url: string;
    created_at: string | null;
}

export interface PlaylistItem {
    id: number;
    position: number;
    duration_seconds: number | null;
    is_active: boolean;
    media: MediaAsset | null;
}

export interface Playlist {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    items: PlaylistItem[];
}

export interface DisplayDevice {
    id: number;
    name: string;
    slug: string;
    token: string;
    layout: DisplayLayout;
    is_active: boolean;
    last_seen_at: string | null;
    url: string;
    sections: Array<{
        id: number;
        slug: string;
        name: string;
        is_active: boolean;
    }>;
    playlists: Array<{
        id: number;
        name: string;
        is_active: boolean;
        items_count: number;
    }>;
}

export interface MediaItem {
    playlist: string;
    media: MediaAsset;
    duration_seconds: number | null;
}

export interface AdminSettings {
    closing_time: string;
    next_closing_at: string;
    timezone: string;
    retention_months: number;
}

export interface PriorityProfile {
    id: number;
    name: string;
    description: string | null;
    general_ratio: number;
    preferential_ratio: number;
    aging_minutes: number;
    max_consecutive_preferential: number;
    is_active: boolean;
    effective_from: string | null;
    effective_to: string | null;
    is_effective_now: boolean;
}

export interface Service {
    id: number;
    slug: string;
    name: string;
    label: string;
    description: string | null;
    is_active: boolean;
    is_available_now: boolean;
    available_days: number[] | null;
    available_from: string | null;
    available_to: string | null;
    effective_from: string | null;
    effective_to: string | null;
    sort_order: number;
    section?: {
        slug: string;
        name: string;
    };
    operators?: Array<{
        id: number;
        name: string;
    }>;
}

export interface SimulatedQueue {
    profile: PriorityProfile;
    sequence: Array<{
        position: number;
        group: string;
        reason: string;
        general_remaining: number;
        preferential_remaining: number;
    }>;
    totals: {
        general: number;
        preferential: number;
    };
}

export interface AdminSection {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    sort_order: number;
    matrix_confirmed_at: string | null;
    matrix_confirmed_by: string | null;
    stations_count?: number;
    codes: Array<{
        id: number;
        code: string;
        sort_order: number;
        is_active: boolean;
        categories: Category[];
    }>;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    default_section: {
        slug: string;
        name: string;
    } | null;
    default_station: {
        id: number;
        name: string;
    } | null;
    created_at: string | null;
}

export interface KioskDevice {
    id: number;
    name: string;
    slug: string;
    token: string;
    is_active: boolean;
    last_seen_at: string | null;
    sections: Array<{
        id: number;
        slug: string;
        name: string;
        is_active: boolean;
    }>;
}

export interface PublicKiosk {
    id: number;
    name: string;
    slug: string;
    printer: {
        driver: 'local' | 'qz';
        target: string | null;
        paper_width: number;
    } | null;
    sections: Section[];
}

export interface Printer {
    id: number;
    name: string;
    driver: 'local' | 'qz';
    target: string | null;
    paper_width: number;
    is_active: boolean;
    config: Record<string, unknown> | null;
    kiosk_device?: {
        id: number;
        name: string;
        slug: string;
    } | null;
}

export interface AuditLogEntry {
    id: number;
    action: string;
    entity_type: string;
    entity_id: number | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
    user?: {
        id: number;
        name: string;
    } | null;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}

export interface ReportFiltersPayload {
    from?: string;
    to?: string;
    section?: string;
    category?: string;
    service?: string;
    station?: string;
}

export interface ReportTotals {
    issued: number;
    waiting: number;
    called: number;
    in_service: number;
    finished: number;
    no_show: number;
    cancelled: number;
    expired: number;
    avg_wait_seconds: number | null;
    avg_service_seconds: number | null;
    avg_total_seconds: number | null;
}

export interface ReportGroupRow {
    issued: number;
    finished: number;
    no_show: number;
    expired: number;
    cancelled: number;
    avg_wait_seconds: number | null;
    avg_service_seconds: number | null;
}

export interface ReportSectionRow extends ReportGroupRow {
    section: {
        id: number;
        slug: string;
        name: string;
    };
}

export interface ReportCategoryRow extends ReportGroupRow {
    category: {
        id: number;
        slug: string;
        name: string;
        is_preferential: boolean;
    };
}

export interface ReportServiceRow extends ReportGroupRow {
    service: {
        id: number | null;
        slug: string | null;
        name: string | null;
        label: string | null;
    };
}

export interface ReportStationRow extends ReportGroupRow {
    station_label: string | null;
}

export interface ReportSummary {
    period: {
        from: string;
        to: string;
    };
    filters: {
        from: string;
        to: string;
        section: string | null;
        category: string | null;
        service: string | null;
        station: string | null;
    };
    totals: ReportTotals;
    by_section: ReportSectionRow[];
    by_category: ReportCategoryRow[];
    by_service: ReportServiceRow[];
    by_station: ReportStationRow[];
}

export interface ReportExport {
    id: number;
    type: string;
    format: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    status_label: string;
    filters: ReportFiltersPayload;
    rows: number;
    file_size: number | null;
    error: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    user?: {
        id: number;
        name: string;
    } | null;
    download_url?: string;
}
