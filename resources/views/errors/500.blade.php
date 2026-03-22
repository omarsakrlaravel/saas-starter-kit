<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>tailwind.config = { darkMode: 'media' }</script>
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex items-center justify-center px-6">
    <div class="w-full max-w-md text-center">
        <p class="text-sm font-semibold tracking-widest uppercase text-zinc-400 dark:text-zinc-500">Error 500</p>

        <h1 class="mt-4 text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-5xl">
            Something went wrong
        </h1>

        <p class="mt-4 text-base leading-relaxed text-zinc-500 dark:text-zinc-400">
            We're working on it. Please try again in a moment.
        </p>

        <div class="mt-8 flex items-center justify-center gap-4">
            <a href="{{ url('/') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 dark:bg-zinc-100 px-5 py-2.5 text-sm font-medium text-white dark:text-zinc-900 shadow-sm transition hover:bg-zinc-700 dark:hover:bg-zinc-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256" fill="currentColor"><path d="M219.31,108.68l-80-80a16,16,0,0,0-22.62,0l-80,80A15.87,15.87,0,0,0,32,120v96a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V160h32v56a8,8,0,0,0,8,8h64a8,8,0,0,0,8-8V120A15.87,15.87,0,0,0,219.31,108.68ZM208,208H160V152a8,8,0,0,0-8-8H104a8,8,0,0,0-8,8v56H48V120l80-80,80,80Z"></path></svg>
                Go home
            </a>
            <button onclick="location.reload()"
                    class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-5 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 shadow-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256" fill="currentColor"><path d="M224,48V96a8,8,0,0,1-8,8H168a8,8,0,0,1,0-16h28.69L182.06,73.37a79.56,79.56,0,0,0-56.13-23.43h-.45A79.52,79.52,0,0,0,69.59,72.71,8,8,0,0,1,58.41,61.27a96,96,0,0,1,135,-.27L208,75.31V48a8,8,0,0,1,16,0Zm-34.27,120a79.57,79.57,0,0,1-56.2,24h-.45a79.52,79.52,0,0,1-55.89-22.77L62.06,154.56H88a8,8,0,0,0,0-16H40a8,8,0,0,0-8,8v48a8,8,0,0,0,16,0V170.56l13.67,14.65A95.43,95.43,0,0,0,128.93,210h.54a95.48,95.48,0,0,0,67.53-28.73,8,8,0,0,0-7.27-13.27Z"></path></svg>
                Try again
            </button>
        </div>
    </div>
</body>
</html>
