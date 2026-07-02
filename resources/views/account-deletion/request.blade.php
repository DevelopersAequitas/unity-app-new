<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Account Deletion | Peers Global Unity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg-start: #0a0f1f;
            --bg-end: #101833;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #f5f7ff;
            --text-secondary: #c6c9e5;
            --accent: #ff6b6b;
            --accent-glow: rgba(255, 107, 107, 0.15);
            --accent-strong: #e63946;
            --shadow: 0 24px 60px rgba(4, 8, 26, 0.45);
        }

        body {
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at top, #1a2247 0%, var(--bg-end) 45%, var(--bg-start) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .deletion-card {
            width: 100%;
            max-width: 550px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            transition: all 0.3s ease;
        }

        .deletion-card:hover {
            border-color: rgba(255, 107, 107, 0.3);
            box-shadow: 0 24px 60px rgba(255, 107, 107, 0.05);
        }

        .header-icon {
            font-size: 3rem;
            color: var(--accent);
            text-shadow: 0 0 20px var(--accent-glow);
            margin-bottom: 20px;
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--text-primary);
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
            color: var(--text-primary);
        }

        .btn-danger {
            background-color: var(--accent-strong);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #ff4d4d;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px var(--accent-glow);
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
            border-radius: 12px;
            padding: 16px;
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            border-radius: 12px;
            padding: 16px;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text-primary);
            margin-bottom: 30px;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="deletion-card text-center">
        <div class="logo-text">
            <i class="bi bi-unity me-2 text-info"></i>PEERS GLOBAL UNITY
        </div>

        <div class="header-icon">
            <i class="bi bi-shield-exclamation"></i>
        </div>

        <h3 class="mb-2">Request Account Deletion</h3>
        <p class="text-muted mb-4">We are sorry to see you go. Please submit your deletion request below. Note that this action is permanent and all your data will be permanently removed.</p>

        @if(session('success'))
            <div class="alert alert-success text-start mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                {{ session('success') }}
            </div>
            <a href="/" class="btn btn-outline-light w-100 py-2.5" style="border-radius: 12px;">Back to Home</a>
        @else
            <form action="{{ route('account-deletion.submit') }}" method="POST" class="text-start">
                @csrf

                @if(session('error'))
                    <div class="alert alert-danger mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @if($user)
                    <div class="mb-4">
                        <label class="form-label">Account details</label>
                        <div class="form-control" style="background: rgba(255, 255, 255, 0.03); color: var(--text-secondary);">
                            <div class="d-flex align-items-center justify-content-between">
                                <span><strong>Name:</strong> {{ $user->display_name ?? $user->first_name }}</span>
                                <span class="badge bg-secondary">Logged In</span>
                            </div>
                            <div class="mt-1"><strong>Email:</strong> {{ $user->email }}</div>
                        </div>
                    </div>
                @else
                    <div class="mb-3">
                        <label for="email" class="form-label">Your Account Email Address</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div class="mb-4">
                    <label for="reason" class="form-label">Reason for deletion</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="4" placeholder="Please let us know why you would like to delete your account..." required>{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="confirm_deletion" required style="cursor: pointer;">
                    <label class="form-check-label text-muted small" for="confirm_deletion" style="cursor: pointer; user-select: none;">
                        I understand that this action is irreversible, and all my profile data, coins balance, and history will be permanently deleted.
                    </label>
                </div>

                <button type="submit" class="btn btn-danger w-100">
                    <i class="bi bi-trash3 me-2"></i>Submit Deletion Request
                </button>
            </form>
        @endif
    </div>

</body>
</html>
