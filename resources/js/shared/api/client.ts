import type {
    AdminSettings,
    AuthUser,
    ConsoleQueue,
    DisplaySnapshot,
    IssuedTicketResponse,
    Operator,
    OperatorSession,
    PrintPayload,
    PriorityProfile,
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

    const response = await fetch(`/api/v1${path}`, {
        ...init,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
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

    snapshot(): Promise<DisplaySnapshot> {
        return request('/display/snapshot');
    },

    adminSettings(): Promise<{ data: AdminSettings }> {
        return request('/admin/settings');
    },

    updateAdminSettings(closingTime: string): Promise<{ data: AdminSettings; message: string }> {
        return request('/admin/settings', {
            method: 'PUT',
            body: JSON.stringify({ closing_time: closingTime }),
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
