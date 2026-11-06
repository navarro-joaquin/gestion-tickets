import http from 'k6/http';
import { check, sleep } from 'k6';
import {
    BASE_URL,
    OPERATOR_EMAIL,
    OPERATOR_PASSWORD,
    SECTION,
    loginTokenFrom,
} from './lib/config.js';

/**
 * Carga de lectura de la consola de operador.
 *
 * Inicia sesión con la cuenta indicada y consulta la cola cada 5 segundos.
 * No ejecuta acciones que muevan tickets: úsalo para medir autenticación,
 * consulta de cola y sesión. Las pruebas de llamado/atención se hacen con el
 * flujo funcional del runbook de operación.
 */
export const options = {
    scenarios: {
        console: {
            executor: 'constant-vus',
            vus: Number(__ENV.CONSOLE_VUS || 5),
            duration: __ENV.DURATION || '2m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

let loggedIn = false;

function login() {
    const loginPage = http.get(`${BASE_URL}/login`);
    const token = loginTokenFrom(loginPage.body);

    if (token === null) {
        throw new Error('No se encontró el token CSRF del formulario de login.');
    }

    const response = http.post(
        `${BASE_URL}/login`,
        {
            _token: token,
            email: OPERATOR_EMAIL,
            password: OPERATOR_PASSWORD,
        },
        {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            redirects: 5,
        },
    );

    check(response, {
        'login completado': (result) => result.status === 200,
    });

    const me = http.get(`${BASE_URL}/api/v1/auth/me`, {
        headers: { Accept: 'application/json' },
    });

    check(me, {
        'sesión autenticada': (result) => result.status === 200,
    });

    loggedIn = me.status === 200;
}

export default function () {
    if (!loggedIn) {
        login();
    }

    const queue = http.get(
        `${BASE_URL}/api/v1/console/queue?section=${encodeURIComponent(SECTION)}`,
        { headers: { Accept: 'application/json' } },
    );

    check(queue, {
        'cola disponible (200)': (result) => result.status === 200,
    });

    sleep(5);
}
