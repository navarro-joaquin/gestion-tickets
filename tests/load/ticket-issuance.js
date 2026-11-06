import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, CATEGORY, JSON_HEADERS, KIOSK_TOKEN, SECTION, csrfTokenFrom } from './lib/config.js';

/**
 * Emisión concurrente de tickets.
 *
 * Objetivo: comprobar que la numeración diaria y la transacción de emisión
 * soportan la demanda de la ventanilla sin duplicados ni bloqueos largos.
 *
 * Ejecutar solo contra un entorno de staging con la base de datos de prueba.
 */
export const options = {
    scenarios: {
        issuance: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 20,
            maxVUs: 120,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '1m', target: 25 },
                { duration: '30s', target: 5 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<800'],
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

let csrfToken = null;

if (!KIOSK_TOKEN) {
    throw new Error('Define KIOSK_TOKEN con el token de un quiosco activo de staging.');
}

function ensureCsrf() {
    if (csrfToken !== null) {
        return csrfToken;
    }

    const response = http.get(`${BASE_URL}/quiosco`);
    csrfToken = csrfTokenFrom(response);

    return csrfToken;
}

export default function () {
    const token = ensureCsrf();

    if (token === null) {
        throw new Error('No se pudo obtener el token CSRF.');
    }

    const response = http.post(
        `${BASE_URL}/api/v1/tickets`,
        JSON.stringify({ kiosk: KIOSK_TOKEN, section: SECTION, category: CATEGORY }),
        {
            headers: { ...JSON_HEADERS, 'X-XSRF-TOKEN': token },
        },
    );

    check(response, {
        'ticket emitido (201)': (result) => result.status === 201,
        'respuesta con número': (result) => {
            try {
                return Number(result.json('data.number')) > 0;
            } catch {
                return false;
            }
        },
    });

    sleep(0.2);
}
