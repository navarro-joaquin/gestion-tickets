import type {
    AdminSection,
    AdminSettings,
    AdminUser,
    AuditLogEntry,
    AuthUser,
    ConsoleQueue,
    DisplayDevice,
    DisplayLayout,
    DisplaySnapshot,
    IssuedTicketResponse,
    KioskDevice,
    MediaAsset,
    Operator,
    OperatorSession,
    Paginated,
    Playlist,
    PrintPayload,
    Printer,
    PriorityProfile,
    PublicKiosk,
    ReportExport,
    ReportFiltersPayload,
    ReportSummary,
    Section,
    Service,
    SimulatedQueue,
    Station,
    Ticket,
} from '../types/api';

export class ApiError extends Error {
    public constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

function csrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const token = csrfToken();
    const isFormData = init.body instanceof FormData;

    const response = await fetch(`/api/v1${path}`, {
        ...init,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            ...(init.headers ?? {}),
        },
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        const errors = (body?.errors ?? {}) as Record<string, string[]>;
        const firstError = Object.values(errors).flat()[0];
        const message = firstError ?? body?.message ?? `Error HTTP ${response.status}`;

        throw new ApiError(response.status, message, errors);
    }

    return body as T;
}

export const api = {
    me(): Promise<{ data: AuthUser }> {
        return request('/auth/me');
    },

    logout(): Promise<{ message: string }> {
        return request('/auth/logout', { method: 'POST' });
    },

    sections(): Promise<{ data: Section[] }> {
        return request('/sections');
    },

    issue(section: string, category?: string, service?: string): Promise<IssuedTicketResponse> {
        return request('/tickets', {
            method: 'POST',
            body: JSON.stringify({ section, category, service }),
        });
    },

    operators(): Promise<{ data: Operator[] }> {
        return request('/console/operators');
    },

    stations(section: string): Promise<{ data: Station[] }> {
        return request(`/console/stations?section=${encodeURIComponent(section)}`);
    },

    queue(section: string, station?: string): Promise<{ data: ConsoleQueue }> {
        const params = new URLSearchParams({ section });

        if (station) {
            params.set('station', station);
        }

        return request(`/console/queue?${params.toString()}`);
    },

    openSession(section: string, stationId: number): Promise<{ data: OperatorSession; message: string }> {
        return request('/console/session', {
            method: 'POST',
            body: JSON.stringify({ section, station_id: stationId }),
        });
    },

    session(sessionId: number): Promise<{ data: OperatorSession }> {
        return request(`/console/session/${sessionId}`);
    },

    closeSession(sessionId: number): Promise<{ data: OperatorSession; message: string }> {
        return request(`/console/session/${sessionId}`, { method: 'DELETE' });
    },

    changeStation(
        sessionId: number,
        stationId: number,
    ): Promise<{ data: OperatorSession; message: string }> {
        return request(`/console/session/${sessionId}/station`, {
            method: 'PATCH',
            body: JSON.stringify({ station_id: stationId }),
        });
    },

    next(section: string, station: string): Promise<{ data: Ticket | null; message: string }> {
        return request('/console/tickets/next', {
            method: 'POST',
            body: JSON.stringify({ section, station }),
        });
    },

    recall(ticketId: number): Promise<{ data: Ticket }> {
        return request(`/console/tickets/${ticketId}/recall`, { method: 'POST' });
    },

    start(ticketId: number): Promise<{ data: Ticket }> {
        return request(`/console/tickets/${ticketId}/start`, { method: 'POST' });
    },

    finish(ticketId: number): Promise<{ data: Ticket }> {
        return request(`/console/tickets/${ticketId}/finish`, { method: 'POST' });
    },

    transfer(
        ticketId: number,
        section: string,
        toOperatorId?: number | null,
        reason?: string,
    ): Promise<{ data: Ticket; message: string }> {
        return request(`/console/tickets/${ticketId}/transfer`, {
            method: 'POST',
            body: JSON.stringify({
                section,
                to_operator_id: toOperatorId ?? undefined,
                reason: reason || undefined,
            }),
        });
    },

    reprint(ticketId: number): Promise<{ data: Ticket; print: PrintPayload; message: string }> {
        return request(`/console/tickets/${ticketId}/reprint`, { method: 'POST' });
    },

    printFailed(ticketId: number, message: string): Promise<{ data: Ticket; message: string }> {
        return request(`/console/tickets/${ticketId}/print-failed`, {
            method: 'POST',
            body: JSON.stringify({ message }),
        });
    },

    snapshot(displayToken?: string): Promise<DisplaySnapshot> {
        const suffix = displayToken ? `?display=${encodeURIComponent(displayToken)}` : '';

        return request(`/display/snapshot${suffix}`);
    },

    adminSettings(): Promise<{ data: AdminSettings }> {
        return request('/admin/settings');
    },

    updateAdminSettings(closingTime: string, retentionMonths?: number): Promise<{ data: AdminSettings; message: string }> {
        return request('/admin/settings', {
            method: 'PUT',
            body: JSON.stringify({
                closing_time: closingTime,
                retention_months: retentionMonths,
            }),
        });
    },

    priorityProfiles(): Promise<{ data: PriorityProfile[] }> {
        return request('/admin/priority-profiles');
    },

    createPriorityProfile(payload: PriorityProfilePayload): Promise<{ data: PriorityProfile; message: string }> {
        return request('/admin/priority-profiles', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updatePriorityProfile(
        id: number,
        payload: PriorityProfilePayload,
    ): Promise<{ data: PriorityProfile; message: string }> {
        return request(`/admin/priority-profiles/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deletePriorityProfile(id: number): Promise<{ message: string }> {
        return request(`/admin/priority-profiles/${id}`, { method: 'DELETE' });
    },

    services(section?: string): Promise<{ data: Service[] }> {
        const params = new URLSearchParams();

        if (section) {
            params.set('section', section);
        }

        const query = params.toString();

        return request(`/admin/services${query ? `?${query}` : ''}`);
    },

    createService(payload: ServicePayload): Promise<{ data: Service; message: string }> {
        return request('/admin/services', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updateService(id: number, payload: ServicePayload): Promise<{ data: Service; message: string }> {
        return request(`/admin/services/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteService(id: number): Promise<{ message: string }> {
        return request(`/admin/services/${id}`, { method: 'DELETE' });
    },

    adminOperators(): Promise<{ data: Operator[] }> {
        return request('/admin/operators');
    },

    simulateQueue(
        generalCount: number,
        preferentialCount: number,
        profileId?: number | null,
    ): Promise<{ data: SimulatedQueue }> {
        return request('/admin/queue/simulate', {
            method: 'POST',
            body: JSON.stringify({
                general_count: generalCount,
                preferential_count: preferentialCount,
                profile_id: profileId ?? undefined,
            }),
        });
    },

    adminSections(): Promise<{ data: AdminSection[] }> {
        return request('/admin/sections');
    },

    updateAdminSection(slug: string, payload: AdminSectionPayload): Promise<{ data: AdminSection; message: string }> {
        return request(`/admin/sections/${encodeURIComponent(slug)}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    updateSectionMatrix(slug: string, payload: MatrixPayload): Promise<{ data: AdminSection; message: string }> {
        return request(`/admin/sections/${encodeURIComponent(slug)}/matrix`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    adminStations(section?: string): Promise<{ data: Station[] }> {
        const query = section ? `?section=${encodeURIComponent(section)}` : '';

        return request(`/admin/stations${query}`);
    },

    createStation(payload: StationPayload): Promise<{ data: Station; message: string }> {
        return request('/admin/stations', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updateStation(id: number, payload: StationPayload): Promise<{ data: Station; message: string }> {
        return request(`/admin/stations/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteStation(id: number): Promise<{ message: string }> {
        return request(`/admin/stations/${id}`, { method: 'DELETE' });
    },

    adminUsers(): Promise<{ data: AdminUser[] }> {
        return request('/admin/users');
    },

    createUser(payload: AdminUserPayload): Promise<{ data: AdminUser; message: string }> {
        return request('/admin/users', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updateUser(id: number, payload: AdminUserPayload): Promise<{ data: AdminUser; message: string }> {
        return request(`/admin/users/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteUser(id: number): Promise<{ message: string }> {
        return request(`/admin/users/${id}`, { method: 'DELETE' });
    },

    adminKiosks(): Promise<{ data: KioskDevice[] }> {
        return request('/admin/kiosks');
    },

    createKiosk(payload: KioskPayload): Promise<{ data: KioskDevice; message: string }> {
        return request('/admin/kiosks', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updateKiosk(id: number, payload: KioskPayload): Promise<{ data: KioskDevice; message: string }> {
        return request(`/admin/kiosks/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteKiosk(id: number): Promise<{ message: string }> {
        return request(`/admin/kiosks/${id}`, { method: 'DELETE' });
    },

    kiosk(token: string): Promise<{ data: PublicKiosk }> {
        return request(`/kiosks/${encodeURIComponent(token)}`);
    },

    adminPrinters(): Promise<{ data: Printer[] }> {
        return request('/admin/printers');
    },

    createPrinter(payload: PrinterPayload): Promise<{ data: Printer; message: string }> {
        return request('/admin/printers', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updatePrinter(id: number, payload: PrinterPayload): Promise<{ data: Printer; message: string }> {
        return request(`/admin/printers/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deletePrinter(id: number): Promise<{ message: string }> {
        return request(`/admin/printers/${id}`, { method: 'DELETE' });
    },

    consolePrinters(stationId?: number): Promise<{ data: Printer[] }> {
        const query = stationId ? `?station_id=${stationId}` : '';

        return request(`/console/printers${query}`);
    },

    auditLogs(params: AuditLogFilters = {}): Promise<Paginated<AuditLogEntry>> {
        const query = new URLSearchParams();

        if (params.action) {
            query.set('action', params.action);
        }

        if (params.entity_type) {
            query.set('entity_type', params.entity_type);
        }

        if (params.page) {
            query.set('page', String(params.page));
        }

        if (params.per_page) {
            query.set('per_page', String(params.per_page));
        }

        const suffix = query.toString();

        return request(`/admin/audit-logs${suffix ? `?${suffix}` : ''}`);
    },

    reportSummary(filters: ReportFiltersPayload): Promise<{ data: ReportSummary }> {
        return request(`/admin/reports/summary${buildQuery({ ...filters })}`);
    },

    reportTickets(
        filters: ReportFiltersPayload & { page?: number; per_page?: number },
    ): Promise<Paginated<Ticket>> {
        return request(`/admin/reports/tickets${buildQuery({ ...filters })}`);
    },

    reportExports(): Promise<Paginated<ReportExport>> {
        return request('/admin/reports/exports');
    },

    createReportExport(filters: ReportFiltersPayload): Promise<{ data: ReportExport; message: string }> {
        return request('/admin/reports/exports', {
            method: 'POST',
            body: JSON.stringify(filters),
        });
    },

    deleteReportExport(id: number): Promise<{ message: string }> {
        return request(`/admin/reports/exports/${id}`, { method: 'DELETE' });
    },

    mediaAssets(): Promise<{ data: MediaAsset[] }> {
        return request('/admin/media');
    },

    createMediaAsset(formData: FormData): Promise<{ data: MediaAsset; message: string }> {
        return request('/admin/media', {
            method: 'POST',
            body: formData,
        });
    },

    updateMediaAsset(id: number, payload: MediaAssetPayload): Promise<{ data: MediaAsset; message: string }> {
        return request(`/admin/media/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteMediaAsset(id: number): Promise<{ message: string }> {
        return request(`/admin/media/${id}`, { method: 'DELETE' });
    },

    playlists(): Promise<{ data: Playlist[] }> {
        return request('/admin/playlists');
    },

    createPlaylist(payload: PlaylistPayload): Promise<{ data: Playlist; message: string }> {
        return request('/admin/playlists', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updatePlaylist(id: number, payload: PlaylistPayload): Promise<{ data: Playlist; message: string }> {
        return request(`/admin/playlists/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deletePlaylist(id: number): Promise<{ message: string }> {
        return request(`/admin/playlists/${id}`, { method: 'DELETE' });
    },

    displays(): Promise<{ data: DisplayDevice[] }> {
        return request('/admin/displays');
    },

    createDisplay(payload: DisplayPayload): Promise<{ data: DisplayDevice; message: string }> {
        return request('/admin/displays', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    updateDisplay(id: number, payload: DisplayPayload): Promise<{ data: DisplayDevice; message: string }> {
        return request(`/admin/displays/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    deleteDisplay(id: number): Promise<{ message: string }> {
        return request(`/admin/displays/${id}`, { method: 'DELETE' });
    },

    rotateDisplayToken(id: number): Promise<{ data: DisplayDevice; message: string }> {
        return request(`/admin/displays/${id}/token`, { method: 'POST' });
    },
};

export interface PriorityProfilePayload {
    name: string;
    description?: string | null;
    general_ratio: number;
    preferential_ratio: number;
    aging_minutes: number;
    max_consecutive_preferential: number;
    is_active: boolean;
    effective_from?: string | null;
    effective_to?: string | null;
}

export interface ServicePayload {
    section: string;
    slug: string;
    name: string;
    label: string;
    description?: string | null;
    is_active: boolean;
    available_days?: number[] | null;
    available_from?: string | null;
    available_to?: string | null;
    effective_from?: string | null;
    effective_to?: string | null;
    sort_order?: number;
    operator_ids?: number[];
}

export interface AdminSectionPayload {
    name: string;
    is_active: boolean;
    sort_order: number;
}

export interface MatrixPayload {
    codes: Array<{
        code: string;
        categories: string[];
    }>;
}

export interface StationPayload {
    section?: string;
    name: string;
    is_active: boolean;
    sort_order: number;
}

export interface AdminUserPayload {
    name: string;
    email: string;
    password?: string;
    role: string;
    is_active: boolean;
}

export interface KioskPayload {
    name: string;
    slug?: string | null;
    is_active: boolean;
    sections: string[];
}

export interface PrinterPayload {
    station_id?: number | null;
    name: string;
    driver: 'local' | 'qz';
    target?: string | null;
    paper_width: number;
    is_active: boolean;
    config?: Record<string, unknown> | null;
}

export interface AuditLogFilters {
    action?: string;
    entity_type?: string;
    page?: number;
    per_page?: number;
}

export interface MediaAssetPayload {
    name: string;
    type: 'image' | 'video';
    duration_seconds: number | null;
    is_active: boolean;
}

export interface PlaylistItemPayload {
    media_asset_id: number;
    duration_seconds: number | null;
    is_active: boolean;
}

export interface PlaylistPayload {
    name: string;
    description: string | null;
    is_active: boolean;
    items: PlaylistItemPayload[];
}

export interface DisplayPayload {
    name: string;
    slug: string | null;
    layout: DisplayLayout;
    is_active: boolean;
    sections: string[];
    playlists: number[];
}

function buildQuery(params: Record<string, string | number | undefined>): string {
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    }

    const suffix = query.toString();

    return suffix ? `?${suffix}` : '';
}
