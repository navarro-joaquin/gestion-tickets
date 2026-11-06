import type {
    ConsoleQueue,
    DisplaySnapshot,
    IssuedTicketResponse,
    Section,
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

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(`/api/v1${path}`, {
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
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
    sections(): Promise<{ data: Section[] }> {
        return request('/sections');
    },

    issue(section: string, category: string): Promise<IssuedTicketResponse> {
        return request('/tickets', {
            method: 'POST',
            body: JSON.stringify({ section, category }),
        });
    },

    queue(section: string, station: string): Promise<{ data: ConsoleQueue }> {
        return request(
            `/console/queue?section=${encodeURIComponent(section)}&station=${encodeURIComponent(station)}`,
        );
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

    snapshot(): Promise<DisplaySnapshot> {
        return request('/display/snapshot');
    },
};
