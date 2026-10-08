@extends('admin.layouts.app')

@section('title', 'Staf & Akses')
@section('heading', 'Staf & Akses')

@section('content')
    @php
        $roleLabels = [
            'ADMIN' => 'Admin',
            'OPERATION_MANAGEMENT' => 'OM (Pembungkusan & Pemenuhan Tempahan)',
            'CUSTOMER_SERVICE' => 'Khidmat Pelanggan',
            'DESIGNER' => 'Designer',
            'PRODUCTION' => 'Pengeluar',
        ];
    @endphp

    <style>
        .admin-workflow-options { display:flex; grid-column:1 / -1; flex-wrap:wrap; gap:10px; }
        .admin-workflow-option { display:flex !important; min-width:245px; margin:0 !important; padding:10px 12px; align-items:center; gap:10px; border:1px solid #d8cba9; border-radius:9px; background:#fffdf8; cursor:pointer; }
        .admin-workflow-option input { width:18px !important; min-width:18px; min-height:18px !important; margin:0; accent-color:#89631d; }
        .admin-workflow-option span { display:grid; gap:2px; }
        .admin-workflow-option strong { color:#28362e; font-size:12px; }
        .admin-workflow-option small { color:#69746f; font-size:10px; line-height:1.35; }
        .admin-workflow-option:hover { border-color:#b9852c; background:#fff9ea; }
        .admin-workflow-option[hidden] { display:none !important; }
    </style>

    <section class="admin-page-intro">
        <div><p class="admin-eyebrow">Kawalan Akses</p><h2>Urus akaun dan jabatan staf</h2><p>Nyahaktifkan akaun untuk akses sementara, atau padam akaun yang tidak lagi diperlukan. Rekod kerja lama kekal disimpan.</p></div>
        <a href="#create-staff" class="admin-button admin-button-primary admin-staff-create-cta">+ Tambah Staf</a>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div><p class="admin-eyebrow">Jejak Keselamatan</p><h2>Aktiviti akaun terkini</h2></div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Masa</th><th>Aktiviti</th><th>Pelaksana</th><th>Akaun Berkaitan</th><th>Alamat IP</th></tr></thead>
                <tbody>
                    @forelse ($recentAuditLogs as $auditLog)
                        <tr>
                            <td>{{ $auditLog->created_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</td>
                            <td>{{ str($auditLog->event_type)->replace('_', ' ')->title() }}</td>
                            <td>{{ $auditLog->actor?->name ?? 'Sistem / Tidak dikenal pasti' }}</td>
                            <td>{{ $auditLog->target?->name ?? ($auditLog->metadata['email'] ?? '-') }}</td>
                            <td>{{ $auditLog->ip_address ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada rekod keselamatan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel" id="create-staff">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Akaun baharu</p><h2>Tambah Staf</h2></div></div>
        <form class="admin-form admin-create-form" method="POST" action="{{ route('admin.staff.store') }}" data-staff-workflow-form>
            @csrf
            <div><label for="new-name">Nama penuh</label><input id="new-name" name="name" value="{{ old('name') }}" placeholder="Contoh: Nur Aisyah" required></div>
            <div><label for="new-email">E-mel</label><input id="new-email" type="email" name="email" value="{{ old('email') }}" placeholder="Contoh: aisyah@kkk.local" required></div>
            <div><label for="new-role">Jabatan / Role</label><select id="new-role" name="role" required data-workflow-role-select>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="admin-workflow-options">
                <label class="admin-workflow-option" data-workflow-role="DESIGNER"><input type="checkbox" name="is_design_lead" value="1" @checked(old('is_design_lead'))><span><strong>Ketua designer</strong><small>Terima tugasan design automatik</small></span></label>
                <label class="admin-workflow-option" data-workflow-role="PRODUCTION"><input type="checkbox" name="is_primary_production" value="1" @checked(old('is_primary_production'))><span><strong>Pengeluar utama</strong><small>Terima tugasan cetakan automatik</small></span></label>
            </div>
            <div><label for="new-password">Kata laluan sementara</label><input id="new-password" type="password" name="password" minlength="8" required></div>
            <div><label for="new-password-confirmation">Ulang kata laluan</label><input id="new-password-confirmation" type="password" name="password_confirmation" minlength="8" required></div>
            <div class="admin-form-submit"><button class="admin-button admin-button-primary admin-staff-create-submit" type="submit">Cipta Akaun</button></div>
        </form>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Direktori</p><h2>Senarai Staf</h2></div></div>
        <form class="admin-filter" method="GET" action="{{ route('admin.staff.index') }}">
            <input name="search" value="{{ request('search') }}" placeholder="Cari nama atau email">
            <select name="role"><option value="">Semua jabatan</option>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
            <button class="admin-button admin-button-secondary" type="submit">Tapis</button>
            @if (request()->hasAny(['search', 'role']))<a href="{{ route('admin.staff.index') }}">Tetapkan Semula</a>@endif
        </form>

        <div class="admin-staff-list">
            @forelse ($staffMembers as $staff)
                <article class="admin-staff-card" @if (! $staff->is_active) data-inactive @endif>
                    <header><div class="admin-staff-identity"><span>{{ strtoupper(substr($staff->name, 0, 1)) }}</span><div><h3>{{ $staff->name }}</h3><p>{{ $staff->email }}</p></div></div><span class="admin-account-status">{{ $staff->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></header>

                    <details>
                        <summary>Urus akaun</summary>
                        <form class="admin-form admin-edit-form" method="POST" action="{{ route('admin.staff.update', $staff) }}" data-staff-workflow-form>
                            @csrf @method('PUT')
                            <div><label>Nama penuh</label><input name="name" value="{{ $staff->name }}" required></div>
                            <div><label>E-mel</label><input type="email" name="email" value="{{ $staff->email }}" required></div>
                            <div><label>Jabatan / Role</label><select name="role" data-workflow-role-select @disabled(auth()->user()->is($staff))>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected($staff->role === $value)>{{ $label }}</option>@endforeach</select>@if (auth()->user()->is($staff))<input type="hidden" name="role" value="ADMIN">@endif</div>
                            <div><label>Status akaun</label><select name="is_active" @disabled(auth()->user()->is($staff))><option value="1" @selected($staff->is_active)>Aktif</option><option value="0" @selected(! $staff->is_active)>Tidak Aktif</option></select>@if (auth()->user()->is($staff))<input type="hidden" name="is_active" value="1">@endif</div>
                            <div class="admin-workflow-options">
                                <label class="admin-workflow-option" data-workflow-role="DESIGNER"><input type="checkbox" name="is_design_lead" value="1" @checked($staff->is_design_lead)><span><strong>Ketua designer</strong><small>Terima tugasan design automatik</small></span></label>
                                <label class="admin-workflow-option" data-workflow-role="PRODUCTION"><input type="checkbox" name="is_primary_production" value="1" @checked($staff->is_primary_production)><span><strong>Pengeluar utama</strong><small>Terima tugasan cetakan automatik</small></span></label>
                            </div>
                            <div class="admin-form-submit"><button class="admin-button admin-button-primary admin-action-hover" type="submit">Simpan Perubahan</button></div>
                        </form>

                        <form class="admin-password-form" method="POST" action="{{ route('admin.staff.password.update', $staff) }}">
                            @csrf @method('PUT')
                            <strong>Tetapkan semula kata laluan</strong>
                            <input type="password" name="password" placeholder="Kata laluan baharu (minimum 8 aksara)" minlength="8" required>
                            <input type="password" name="password_confirmation" placeholder="Ulang kata laluan baharu" minlength="8" required>
                            <button class="admin-button admin-button-secondary admin-action-hover" type="submit">Tetapkan Semula</button>
                        </form>

                        @unless (auth()->user()->is($staff))
                            <form class="admin-delete-form" method="POST" action="{{ route('admin.staff.destroy', $staff) }}" onsubmit="return confirm('Adakah anda ingin memadam akaun ini?');">
                                @csrf @method('DELETE')
                                <div>
                                    <strong>Padam akaun</strong>
                                    <small>Rekod operasi dikekalkan, tetapi pengguna ini tidak boleh log masuk semula.</small>
                                </div>
                                <button class="admin-button admin-button-danger admin-action-hover admin-action-hover-danger" type="submit">Padam Akaun</button>
                            </form>
                        @endunless
                    </details>
                </article>
            @empty
                <p class="admin-empty">Tiada akaun staff sepadan dengan carian.</p>
            @endforelse
        </div>

        {{ $staffMembers->links() }}
    </section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-staff-workflow-form]').forEach(function (form) {
        const role = form.querySelector('[data-workflow-role-select]');
        const options = form.querySelectorAll('[data-workflow-role]');

        function syncWorkflowOptions() {
            options.forEach(function (option) {
                const isRelevant = role && role.value === option.dataset.workflowRole;
                option.hidden = !isRelevant;
                option.querySelector('input').disabled = !isRelevant;
            });
        }

        role?.addEventListener('change', syncWorkflowOptions);
        syncWorkflowOptions();
    });
</script>
@endpush
