<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reset Password') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; }
        .card { max-width: 380px; width: 100%; background: #1e293b; border-radius: 20px; padding: 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { font-size: 20px; margin: 0 0 8px 0; }
        p.sub { color: #94a3b8; font-size: 13px; margin: 0 0 24px 0; }
        label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #cbd5e1; margin-top: 14px; }
        input { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; font-size: 14px; }
        button { width: 100%; margin-top: 20px; padding: 12px; border-radius: 12px; border: none; background: #2563eb; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .status { margin-top: 16px; padding: 10px 14px; border-radius: 10px; font-size: 13px; }
        .status.ok { background: rgba(16,185,129,0.15); color: #6ee7b7; }
        .status.err { background: rgba(244,63,94,0.15); color: #fda4af; }
        a { color: #60a5fa; text-decoration: none; font-size: 13px; }
        .back { display: block; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Choose a new password') }}</h1>
        <p class="sub">{{ __('This link will expire 60 minutes after it was requested.') }}</p>

        @if (session('status'))
            <div class="status ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="status err">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="status err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('tenant.password.reset') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">{{ __('Email address') }}</label>
            <input type="email" id="email" name="email" required value="{{ old('email', $email) }}">

            <label for="password">{{ __('New password') }}</label>
            <input type="password" id="password" name="password" required minlength="6">

            <label for="password_confirmation">{{ __('Confirm new password') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6">

            <button type="submit">{{ __('Reset Password') }}</button>
        </form>

        <a class="back" href="{{ route('tenant.login') }}">&larr; {{ __('Back to sign in') }}</a>
    </div>
</body>
</html>
