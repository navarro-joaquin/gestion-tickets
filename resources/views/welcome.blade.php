<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CESSA · Gestión de Tickets</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-brand-950 text-white">
    <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-6 py-16 text-center">
        <div class="rounded-3xl bg-white p-4 shadow-2xl">
            <img src="/images/cessa-logo.jpg" alt="CESSA" class="h-20 w-auto object-contain">
        </div>

        <p class="mt-8 text-sm font-semibold uppercase tracking-[0.35em] text-accent-300">CESSA</p>
        <h1 class="mt-3 text-4xl font-semibold sm:text-5xl">Gestión de tickets</h1>
        <p class="mt-4 max-w-xl text-brand-100">
            Sistema de atención por turnos para Plataforma, ODECO y Cajas.
        </p>

        <div class="mt-10 flex flex-wrap justify-center gap-3">
            <a
                href="{{ route('login') }}"
                class="rounded-xl bg-accent-300 px-6 py-3 font-semibold text-brand-950 transition hover:bg-accent-200"
            >
                Iniciar sesión
            </a>
            <a
                href="{{ route('kiosk') }}"
                class="rounded-xl border border-brand-700 px-6 py-3 font-semibold text-brand-100 transition hover:border-accent-300 hover:text-accent-200"
            >
                Quiosco
            </a>
            <a
                href="{{ route('display') }}"
                class="rounded-xl border border-brand-700 px-6 py-3 font-semibold text-brand-100 transition hover:border-accent-300 hover:text-accent-200"
            >
                Pantalla
            </a>
        </div>
    </main>

    <footer class="border-t border-brand-800 px-6 py-5 text-center text-xs uppercase tracking-[0.2em] text-brand-300">
        CESSA · Plataforma, ODECO y Cajas
    </footer>
</body>
</html>
