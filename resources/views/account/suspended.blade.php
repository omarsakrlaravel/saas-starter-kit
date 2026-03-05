<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Suspended</title>
        <style>
            body { font-family: Inter, Arial, sans-serif; background: #f4f6f8; color: #111827; margin: 0; }
            .wrapper { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .card { width: min(720px, 100%); background: #ffffff; border-radius: 14px; border: 1px solid #d1d5db; padding: 24px; }
            .title { margin: 0 0 8px; font-size: 28px; }
            .body { margin: 0 0 16px; color: #374151; line-height: 1.55; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <main class="card">
                <h1 class="title">Account suspended</h1>
                <p class="body">This account is suspended and cannot access the platform.</p>
                <p class="body">Please contact support and provide the following reference code.</p>
                <p class="mono">{{ $support_reference }}</p>
            </main>
        </div>
    </body>
</html>
