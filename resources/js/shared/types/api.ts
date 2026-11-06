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

export interface Ticket {
    id: number;
    code: string;
    number: number;
    display_number: string;
    label: string;
    status: string;
    status_label: string;
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
    category?: Category;
}

export interface IssuedTicketResponse {
    data: Ticket;
    print: {
        escpos_base64: string;
        text: string;
    };
}

export interface ConsoleQueue {
    section: {
        slug: string;
        name: string;
    };
    waiting_count: number;
    active_ticket: Ticket | null;
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
