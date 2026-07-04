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
            color-scheme: light;
            --bg-color: #f9f9f9;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent: #dc2626;
            --accent-glow: rgba(220, 38, 38, 0.08);
            --accent-strong: #b91c1c;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
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
            transition: all 0.3s ease;
        }

        .deletion-card:hover {
            border-color: rgba(220, 38, 38, 0.2);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: var(--text-primary);
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: #ffffff;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
            color: var(--text-primary);
        }

        .btn-danger {
            background-color: var(--accent);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-danger:hover {
            background-color: var(--accent-strong);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px var(--accent-glow);
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 12px;
            padding: 16px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 12px;
            padding: 16px;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text-primary);
            margin-bottom: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }
    </style>
</head>
<body>

    <div class="deletion-card text-center">
        <div class="logo-text">
            <img src="{{ asset('images/peersglobal-logo.png') }}" alt="Peers Global Unity Logo" class="me-2" style="height: 32px; width: auto; object-fit: contain;">
            PEERS GLOBAL UNITY
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
            <a href="/" class="btn btn-outline-dark w-100 py-2.5" style="border-radius: 12px;">Back to Home</a>
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
                        <div class="form-control" style="background: #f8fafc; border: 1px solid #e2e8f0; color: var(--text-secondary);">
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
