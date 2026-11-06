/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<Record<string, never>, Record<string, never>, unknown>;
    export default component;
}

declare module 'qz-tray';

interface ImportMetaEnv {
    readonly VITE_REVERB_APP_KEY?: string;
    readonly VITE_REVERB_HOST?: string;
    readonly VITE_REVERB_PORT?: string;
    readonly VITE_REVERB_SCHEME?: string;
    readonly VITE_PRINT_DRIVER?: string;
    readonly VITE_PRINT_SERVICE_URL?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
