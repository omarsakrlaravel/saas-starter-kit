<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>tailwind.config = { darkMode: 'media' }</script>
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center px-6">
    <div class="w-full max-w-md text-center">
        <p class="text-sm font-semibold tracking-widest uppercase text-zinc-400 dark:text-zinc-500">Error 403</p>

        <h1 class="mt-4 text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-5xl">
            Access denied
        </h1>

        <p class="mt-4 text-base leading-relaxed text-zinc-500 dark:text-zinc-400">
            {{ $exception->getMessage() ?: "You don't have permission to access this page." }}
        </p>

        <div class="mt-8 flex items-center justify-center gap-4">
            <a href="{{ url('/') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 dark:bg-zinc-100 px-5 py-2.5 text-sm font-medium text-white dark:text-zinc-900 shadow-sm transition hover:bg-zinc-700 dark:hover:bg-zinc-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256" fill="currentColor"><path d="M219.31,108.68l-80-80a16,16,0,0,0-22.62,0l-80,80A15.87,15.87,0,0,0,32,120v96a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V160h32v56a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V120A15.87,15.87,0,0,0,219.31,108.68ZM208,208H160V152a8,8,0,0,0-8-8H104a8,8,0,0,0-8,8v56H48V120l80-80,80,80Z"></path></svg>
                Go home
            </a>
            <button onclick="history.back()"
                    class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-5 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 shadow-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a8,8,0,0,1-8,8H59.31l58.35,58.34a8,8,0,0,1-11.32,11.32l-72-72a8,8,0,0,1,0-11.32l72-72a8,8,0,0,1,11.32,11.32L59.31,120H216A8,8,0,0,1,224,128Z"></path></svg>
                Go back
            </button>
        </div>
    </div>
</body>
</html>
