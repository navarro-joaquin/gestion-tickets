export type PrintDriverKind = 'local' | 'qz';

export interface PrintDriver {
    print(escposBase64: string): Promise<void>;
}

class LocalServiceDriver implements PrintDriver {
    public constructor(
        private readonly url: string,
        private readonly printer?: string,
    ) {}

    public async print(escposBase64: string): Promise<void> {
        const response = await fetch(`${this.url.replace(/\/$/, '')}/print`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                data: escposBase64,
                printer: this.printer,
            }),
        });

        if (!response.ok) {
            throw new Error(`El servicio local de impresión respondió ${response.status}.`);
        }
    }
}

class QzTrayDriver implements PrintDriver {
    public async print(escposBase64: string): Promise<void> {
        const module = await import('qz-tray');
        const qz = ((module as unknown as { default?: unknown }).default ?? module) as {
            websocket: { isActive(): boolean; connect(): Promise<void> };
            printers: { getDefault(): Promise<string> };
            configs: { create(printer: string): unknown };
            print(config: unknown, data: unknown[]): Promise<void>;
        };

        if (!qz.websocket.isActive()) {
            await qz.websocket.connect();
        }

        const printer = await qz.printers.getDefault();

        await qz.print(qz.configs.create(printer), [
            { type: 'raw', format: 'base64', data: escposBase64 },
        ]);
    }
}

export function createPrintDriver(kind: PrintDriverKind): PrintDriver {
    if (kind === 'qz') {
        return new QzTrayDriver();
    }

    return new LocalServiceDriver(
        import.meta.env.VITE_PRINT_SERVICE_URL ?? 'http://127.0.0.1:9100',
    );
}
