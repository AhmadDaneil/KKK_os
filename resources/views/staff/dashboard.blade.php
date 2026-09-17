<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard - KKK OS</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body>
    <div class="staff-shell">
        <header class="staff-header">
            <div class="staff-header-inner">
                <div class="staff-brand">
                    <strong>KKK OS</strong>
                    <span>Staff Operations</span>
                </div>

                <div class="staff-user">
                    <div class="staff-user-meta">
                        <span class="staff-user-name">
                            {{ auth()->user()->name }}
                        </span>

                        <span class="staff-role">
                            {{ auth()->user()->role }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('staff.logout') }}">
                        @csrf

                        <button
                            type="submit"
                            class="staff-button staff-button-small"
                        >
                            Log Keluar
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="staff-main">
            <div class="staff-page-header">
                <div>
                    <h1>Staff Dashboard</h1>

                    <p>
                        Urus dan pantau kerja operasi KKK OS berdasarkan
                        tugasan yang diberikan kepada anda.
                    </p>
                </div>
            </div>

            <div class="staff-grid">
                <a
                    href="{{ route('staff.orders.index') }}"
                    class="staff-card"
                >
                    <h2>Orders</h2>

                    <p>
                        Lihat order dan kerja operasi yang tersedia
                        berdasarkan akses staff anda.
                    </p>
                </a>

                @if (auth()->user()->isAdmin())
                    <div class="staff-card">
                        <h2>Admin Operations</h2>

                        <p>
                            Assignment dan reassignment staff akan
                            dikendalikan dari order yang berkaitan.
                        </p>
                    </div>
                @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                    <div class="staff-card">
                        <h2>Design Queue</h2>

                        <p>
                            Order yang mempunyai design job assigned
                            kepada anda tersedia melalui Orders.
                        </p>
                    </div>
                @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRINTING))
                    <div class="staff-card">
                        <h2>Printing Queue</h2>

                        <p>
                            Order yang mempunyai print job assigned
                            kepada anda tersedia melalui Orders.
                        </p>
                    </div>
                @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING))
                    <div class="staff-card">
                        <h2>Packing Queue</h2>

                        <p>
                            Order dengan packing job assigned kepada
                            anda tersedia melalui Orders.
                        </p>
                    </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>