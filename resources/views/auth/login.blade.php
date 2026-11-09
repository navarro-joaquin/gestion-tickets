<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · CESSA Gestión de Tickets</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-brand-950 px-6 py-10 text-white">
    <main class="w-full max-w-md rounded-3xl border border-brand-800 bg-white p-8 text-slate-900 shadow-2xl">
        <div class="flex items-center gap-4">
            <img
                src="/images/cessa-logo.jpg"
                alt="CESSA"
                class="h-16 w-auto rounded-xl border border-slate-200 object-contain"
            >
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-700">CESSA</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">Gestión de tickets</h1>
            </div>
        </div>

        <p class="mt-6 text-sm text-slate-500">
            Acceso para operadores, supervisores y administradores.
        </p>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
            @csrf

            <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                Correo electrónico
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-200"
                >
            </label>

            <label class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                Contraseña
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-200"
                >
            </label>

            <label class="flex items-center gap-3 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-300">
                Recordarme
            </label>

            <button
                type="submit"
                class="w-full rounded-xl bg-accent-300 px-6 py-3 font-semibold text-brand-950 transition hover:bg-accent-200 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2"
            >
                Entrar
            </button>
        </form>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-500">
            Entorno local: <code class="text-slate-700">operador@demo.local</code> / <code class="text-slate-700">password</code>.
            En producción no se crea este usuario.
        </div>

        <p class="mt-6 text-center text-xs uppercase tracking-[0.2em] text-slate-400">
            CESSA · Sistema de gestión de tickets
        </p>
    </main>
</body>
</html>
