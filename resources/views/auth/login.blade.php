<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Gestión de Tickets</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-950 px-6 py-10 text-white">
    <main class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
        <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Gestión de tickets</p>
        <h1 class="mt-3 text-3xl font-semibold">Iniciar sesión</h1>
        <p class="mt-2 text-sm text-slate-400">
            Acceso para operadores, supervisores y administradores.
        </p>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
            @csrf

            <label class="flex flex-col gap-2 text-sm text-slate-300">
                Correo electrónico
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-sky-500"
                >
            </label>

            <label class="flex flex-col gap-2 text-sm text-slate-300">
                Contraseña
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-sky-500"
                >
            </label>

            <label class="flex items-center gap-3 text-sm text-slate-400">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-700 bg-slate-950">
                Recordarme
            </label>

            <button
                type="submit"
                class="w-full rounded-xl bg-sky-500 px-6 py-3 font-semibold text-slate-950 transition hover:bg-sky-400"
            >
                Entrar
            </button>
        </form>

        <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-950 p-4 text-xs text-slate-500">
            Entorno local: <code class="text-slate-300">operador@demo.local</code> / <code class="text-slate-300">password</code>.
            En producción no se crea este usuario.
        </div>
    </main>
</body>
</html>
