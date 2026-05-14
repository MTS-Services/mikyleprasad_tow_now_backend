<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>FCM test push — {{ config('app.name') }}</title>
    <style>
        :root { font-family: system-ui, sans-serif; line-height: 1.5; color: #111; }
        body { margin: 0; padding: 1.5rem; background: #f6f7f9; }
        .wrap { max-width: 40rem; margin: 0 auto; background: #fff; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        .hint { font-size: 0.875rem; color: #555; margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.35rem; }
        select, input[type="text"], textarea { width: 100%; box-sizing: border-box; padding: 0.5rem 0.65rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem; }
        textarea { min-height: 5rem; font-family: ui-monospace, monospace; font-size: 0.8rem; resize: vertical; }
        .field { margin-bottom: 1rem; }
        .hidden { display: none !important; }
        .actions { margin-top: 1.25rem; }
        button[type="submit"] {
            padding: 0.55rem 1.25rem; font-size: 0.95rem; font-weight: 600;
            border: none; border-radius: 6px; cursor: pointer;
            background: #2563eb; color: #fff;
        }
        button[type="submit"]:disabled { opacity: 0.45; cursor: not-allowed; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        ul.errors { margin: 0 0 1rem; padding-left: 1.2rem; color: #991b1b; font-size: 0.875rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>FCM test push</h1>
    <p class="hint">
        Local / staging only (same guard as API test routes). Sends via Laravel Firebase (server SDK).
        Choose a user who has registered an FCM token, or leave the user unset and paste a token manually.
    </p>
    <p class="hint" style="background:#f0f9ff;border:1px solid #bae6fd;padding:0.75rem;border-radius:6px;color:#0c4a6e;">
        If you see <strong>invalid_grant</strong> or <strong>Invalid JWT Signature</strong>, the problem is the
        <strong>service account JSON</strong> (not FCM tokens). Run
        <code style="background:#e0f2fe;padding:0.1rem 0.35rem;border-radius:4px;">php artisan firebase:verify-credentials</code>
        in the backend folder, then replace the key from Firebase Console → Project settings → Service accounts → Generate new private key.
        See <a href="https://firebase-php.readthedocs.io/8.2.0/setup.html" target="_blank" rel="noopener">Firebase PHP setup</a>.
    </p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('dev.fcm-test-push.send') }}" id="fcm-form">
        @csrf

        <div class="field">
            <label for="user_id">User (has FCM token)</label>
            <select name="user_id" id="user_id">
                <option value="">— None (enter token below) —</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>
                        #{{ $u->id }} — {{ $u->name }} &lt;{{ $u->email }}&gt;
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field hidden" id="manual-token-block">
            <label for="fcm_token">FCM device token (manual)</label>
            <textarea name="fcm_token" id="fcm_token" placeholder="Paste registration token…">{{ old('fcm_token') }}</textarea>
        </div>

        <div class="field">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title', __('api.notification_test_push_default_title')) }}" maxlength="255">
        </div>

        <div class="field">
            <label for="body">Body</label>
            <input type="text" name="body" id="body" value="{{ old('body', __('api.notification_test_push_default_body')) }}" maxlength="2000">
        </div>

        <div class="actions">
            <button type="submit" id="send-btn" disabled>Send</button>
        </div>
    </form>
</div>

<script>
(function () {
    var select = document.getElementById('user_id');
    var manualBlock = document.getElementById('manual-token-block');
    var manualInput = document.getElementById('fcm_token');
    var btn = document.getElementById('send-btn');

    function sync() {
        var uid = select.value;
        var hasUser = uid !== '' && uid !== null;
        manualBlock.classList.toggle('hidden', hasUser);
        if (hasUser) {
            manualInput.value = '';
        }
        var manualOk = !hasUser && manualInput.value.trim().length >= 10;
        var userOk = hasUser;
        btn.disabled = !(manualOk || userOk);
    }

    select.addEventListener('change', sync);
    manualInput.addEventListener('input', sync);
    sync();
})();
</script>
</body>
</html>
