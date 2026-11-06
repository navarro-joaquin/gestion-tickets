// Configuración compartida de los escenarios k6.
export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
export const SECTION = __ENV.SECTION || 'plataforma';
export const CATEGORY = __ENV.CATEGORY || 'general';
export const DISPLAY_TOKEN = __ENV.DISPLAY_TOKEN || '';
export const KIOSK_TOKEN = __ENV.KIOSK_TOKEN || '';
export const OPERATOR_EMAIL = __ENV.OPERATOR_EMAIL || 'operador@demo.local';
export const OPERATOR_PASSWORD = __ENV.OPERATOR_PASSWORD || 'password';

export const JSON_HEADERS = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
};

/**
 * Obtiene el token CSRF de una sesión de Laravel.
 * k6 mantiene las cookies por VU, así que cada VU obtiene su propia sesión.
 */
export function csrfTokenFrom(response) {
    const cookies = response.cookies['XSRF-TOKEN'];

    if (!cookies || cookies.length === 0) {
        return null;
    }

    return decodeURIComponent(cookies[0].value);
}

export function loginTokenFrom(html) {
    const match = html.match(/name="_token"\s+value="([^"]+)"/);

    return match ? match[1] : null;
}
