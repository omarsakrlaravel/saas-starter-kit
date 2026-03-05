<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Restricted</title>
        <style>
            body { font-family: Inter, Arial, sans-serif; background: #f4f6f8; color: #111827; margin: 0; }
            .wrapper { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .card { width: min(760px, 100%); background: #ffffff; border-radius: 14px; border: 1px solid #d1d5db; padding: 24px; }
            .title { margin: 0 0 8px; font-size: 28px; }
            .body { margin: 0 0 16px; color: #374151; line-height: 1.55; }
            .muted { color: #6b7280; }
            .links { display: grid; gap: 10px; margin-top: 12px; }
            .link { display: inline-flex; justify-content: center; align-items: center; border: 1px solid #9ca3af; border-radius: 10px; padding: 10px 14px; text-decoration: none; color: #111827; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <main class="card">
                <h1 class="title">Account access is restricted</h1>
                <p class="body">A review is required on your account before full access can resume.</p>

                @if ($reason)
                    <p class="body">Reason: {{ $reason }}</p>
                @else
                    <p class="body">Reason is not specified yet.</p>
                @endif

                @if (! is_null($days_remaining))
                    <p class="body">Grace period: {{ $days_remaining }} day(s) remaining before a full suspension may be applied.</p>
                @endif

                @if ($expires_at)
                    <p class="body">Last status update: {{ $expires_at }}</p>
                @endif

                <p class="body">{{ $action_hint }}</p>
                <p class="muted">What you can do next:</p>
                <div class="links">
                    <a class="link" href="/billing">Update billing</a>
                    <a class="link" href="/support">Contact support</a>
                    <a class="link" href="/data-export">Export data</a>
                </div>
            </main>
        </div>
    </body>
</html>
