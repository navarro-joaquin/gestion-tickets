export interface Category {
    slug: string;
    name: string;
    is_preferential: boolean;
}

export interface TicketCode {
    code: string;
    categories: Category[];
}

export interface Section {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    matrix_confirmed_at: string | null;
    codes: TicketCode[];
}

export interface Station {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
}

export interface Operator {
    id: number;
    name: string;
    role: string;
    role_label: string;
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

export interface Ticket {
    id: number;
    code: string;
    number: number;
    display_number: string;
    label: string;
    status: string;
    status_label: string;
    is_transferred: boolean;
    station_label: string | null;
    call_count: number;
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
    updated_at: string;
}

export interface AdminSettings {
    closing_time: string;
    next_closing_at: string;
    timezone: string;
}
