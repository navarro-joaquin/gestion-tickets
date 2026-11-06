import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, DISPLAY_TOKEN } from './lib/config.js';

/**
 * Polling de pantallas públicas.
 *
 * Cada pantalla consulta el snapshot cada 5 segundos. El escenario simula
 * varias pantallas y mide la latencia del snapshot y del filtrado por token.
 */
export const options = {
    scenarios: {
        displays: {
            executor: 'constant-vus',
            vus: Number(__ENV.DISPLAY_VUS || 20),
            duration: __ENV.DURATION || '2m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<400'],
        http_req_failed: ['rate<0.005'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    const suffix = DISPLAY_TOKEN === ''
        ? ''
        : `?display=${encodeURIComponent(DISPLAY_TOKEN)}`;

    const response = http.get(`${BASE_URL}/api/v1/display/snapshot${suffix}`);

    check(response, {
        'snapshot disponible (200)': (result) => result.status === 200,
        'snapshot con datos': (result) => {
            try {
                return Array.isArray(result.json('data'));
            } catch {
                return false;
            }
        },
    });

    sleep(5);
}
