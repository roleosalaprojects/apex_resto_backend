<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Apex SuperAdmin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --danger: #ef4444;
            --dark: #1e293b;
            --darker: #0f172a;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 50%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 1rem;
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4);
        }

        .login-brand-text {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .login-brand-text span {
            background: linear-gradient(135deg, var(--primary) 0%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 14px;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 14px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-danger ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-left: 2.75rem;
        }

        .input-group-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background: white;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #64748b;
            font-size: 13px;
        }

        .login-footer a {
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-brand">
            <div class="login-brand-icon">A</div>
            <div class="login-brand-text">Apex<span>Admin</span></div>
        </div>

        <div class="login-card">
            <h1 class="login-title">Welcome back</h1>
            <p class="login-subtitle">Sign in to access the super admin panel</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ url('superadmin/login') }}" method="post">
                @csrf
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                    Sign In
                </button>
            </form>
        </div>

        <div class="login-footer">
            &copy; {{ date('Y') }} Apex POS. All rights reserved.
        </div>
    </div>
</body>
</html>
