import type {
    AdminSettings,
    ConsoleQueue,
    DisplaySnapshot,
    IssuedTicketResponse,
    Operator,
    OperatorSession,
    PrintPayload,
    Section,
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

    operators(): Promise<{ data: Operator[] }> {
        return request('/console/operators');
    },

    stations(section: string): Promise<{ data: Station[] }> {
        return request(`/console/stations?section=${encodeURIComponent(section)}`);
    },

    queue(
        section: string,
        station: string,
        operatorId?: number | null,
    ): Promise<{ data: ConsoleQueue }> {
        const params = new URLSearchParams({ section, station });

        if (operatorId) {
            params.set('operator_id', String(operatorId));
        }

        return request(`/console/queue?${params.toString()}`);
    },

    openSession(
        operatorId: number,
        section: string,
        stationId: number,
    ): Promise<{ data: OperatorSession; message: string }> {
        return request('/console/session', {
            method: 'POST',
            body: JSON.stringify({
                operator_id: operatorId,
                section,
                station_id: stationId,
            }),
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

    next(
        section: string,
        station: string,
        operatorId?: number | null,
    ): Promise<{ data: Ticket | null; message: string }> {
        return request('/console/tickets/next', {
            method: 'POST',
            body: JSON.stringify({
                section,
                station,
                operator_id: operatorId ?? undefined,
            }),
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
        fromOperatorId?: number | null,
        toOperatorId?: number | null,
        reason?: string,
    ): Promise<{ data: Ticket; message: string }> {
        return request(`/console/tickets/${ticketId}/transfer`, {
            method: 'POST',
            body: JSON.stringify({
                section,
                from_operator_id: fromOperatorId ?? undefined,
                to_operator_id: toOperatorId ?? undefined,
                reason: reason || undefined,
            }),
        });
    },

    reprint(ticketId: number): Promise<{ data: Ticket; print: PrintPayload; message: string }> {
        return request(`/console/tickets/${ticketId}/reprint`, { method: 'POST' });
    },

    printFailed(
        ticketId: number,
        message: string,
        operatorId?: number | null,
    ): Promise<{ data: Ticket; message: string }> {
        return request(`/console/tickets/${ticketId}/print-failed`, {
            method: 'POST',
            body: JSON.stringify({
                message,
                operator_id: operatorId ?? undefined,
            }),
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
};
