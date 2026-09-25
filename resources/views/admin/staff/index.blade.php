@extends('admin.layouts.app')

@section('title', 'Staff & Akses')
@section('heading', 'Staff & Akses')

@section('content')
    @php
        $roleLabels = [
            'ADMIN' => 'Admin',
            'OPERATION_MANAGEMENT' => 'OM (Packing & Fulfilment)',
            'CUSTOMER_SERVICE' => 'Customer Service',
            'DESIGNER' => 'Designer',
            'PRODUCTION' => 'Production',
        ];
    @endphp

    <section class="admin-page-intro">
        <div><p class="admin-eyebrow">Access Control</p><h2>Urus akaun dan jabatan staff</h2><p>Nyahaktifkan akaun untuk akses sementara, atau padam akaun yang tidak lagi diperlukan. Rekod kerja lama kekal disimpan.</p></div>
        <a href="#create-staff" class="admin-button admin-button-primary admin-staff-create-cta">+ Tambah Staff</a>
    </section>

    <section class="admin-panel" id="create-staff">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Akaun baharu</p><h2>Tambah Staff</h2></div></div>
        <form class="admin-form admin-create-form" method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <div><label for="new-name">Nama penuh</label><input id="new-name" name="name" value="{{ old('name') }}" placeholder="e.g. Nur Aisyah" required></div>
            <div><label for="new-email">Email</label><input id="new-email" type="email" name="email" value="{{ old('email') }}" placeholder="e.g. aisyah@kkk.local" required></div>
            <div><label for="new-role">Jabatan / Role</label><select id="new-role" name="role" required>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="new-password">Kata laluan sementara</label><input id="new-password" type="password" name="password" minlength="8" required></div>
            <div><label for="new-password-confirmation">Ulang kata laluan</label><input id="new-password-confirmation" type="password" name="password_confirmation" minlength="8" required></div>
            <div class="admin-form-submit"><button class="admin-button admin-button-primary admin-staff-create-submit" type="submit">Cipta Akaun</button></div>
        </form>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Directory</p><h2>Senarai Staff</h2></div></div>
        <form class="admin-filter" method="GET" action="{{ route('admin.staff.index') }}">
            <input name="search" value="{{ request('search') }}" placeholder="Cari nama atau email">
            <select name="role"><option value="">Semua jabatan</option>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
            <button class="admin-button admin-button-secondary" type="submit">Tapis</button>
            @if (request()->hasAny(['search', 'role']))<a href="{{ route('admin.staff.index') }}">Reset</a>@endif
        </form>

        <div class="admin-staff-list">
            @forelse ($staffMembers as $staff)
                <article class="admin-staff-card" @if (! $staff->is_active) data-inactive @endif>
                    <header><div class="admin-staff-identity"><span>{{ strtoupper(substr($staff->name, 0, 1)) }}</span><div><h3>{{ $staff->name }}</h3><p>{{ $staff->email }}</p></div></div><span class="admin-account-status">{{ $staff->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></header>

                    <details>
                        <summary>Urus akaun</summary>
                        <form class="admin-form admin-edit-form" method="POST" action="{{ route('admin.staff.update', $staff) }}">
                            @csrf @method('PUT')
                            <div><label>Nama penuh</label><input name="name" value="{{ $staff->name }}" required></div>
                            <div><label>Email</label><input type="email" name="email" value="{{ $staff->email }}" required></div>
                            <div><label>Jabatan / Role</label><select name="role" @disabled(auth()->user()->is($staff))>@foreach ($roleLabels as $value => $label)<option value="{{ $value }}" @selected($staff->role === $value)>{{ $label }}</option>@endforeach</select>@if (auth()->user()->is($staff))<input type="hidden" name="role" value="ADMIN">@endif</div>
                            <div><label>Status akaun</label><select name="is_active" @disabled(auth()->user()->is($staff))><option value="1" @selected($staff->is_active)>Aktif</option><option value="0" @selected(! $staff->is_active)>Tidak Aktif</option></select>@if (auth()->user()->is($staff))<input type="hidden" name="is_active" value="1">@endif</div>
                            <div class="admin-form-submit"><button class="admin-button admin-button-primary admin-action-hover" type="submit">Simpan Perubahan</button></div>
                        </form>

                        <form class="admin-password-form" method="POST" action="{{ route('admin.staff.password.update', $staff) }}">
                            @csrf @method('PUT')
                            <strong>Reset kata laluan</strong>
                            <input type="password" name="password" placeholder="Kata laluan baharu (minimum 8 aksara)" minlength="8" required>
                            <input type="password" name="password_confirmation" placeholder="Ulang kata laluan baharu" minlength="8" required>
                            <button class="admin-button admin-button-secondary admin-action-hover" type="submit">Tetapkan Semula</button>
                        </form>

                        @unless (auth()->user()->is($staff))
                            <form class="admin-delete-form" method="POST" action="{{ route('admin.staff.destroy', $staff) }}" onsubmit="return confirm('Padam akaun {{ addslashes($staff->name) }}? Tindakan ini tidak boleh dibatalkan. Tugasan aktif akan menjadi belum di-assign.');">
                                @csrf @method('DELETE')
                                <div><strong>Padam akaun</strong><small>Rekod operasi dikekalkan, tetapi pengguna ini tidak boleh log masuk semula.</small></div>
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
