<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KKK OS - {{ $order->order_id }}</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 980px; margin: 32px auto; padding: 0 16px; line-height: 1.4; }
        fieldset { margin: 24px 0; padding: 18px; }
        label { display: block; margin-top: 12px; font-weight: 600; }
        input, textarea, select { width: 100%; box-sizing: border-box; padding: 9px; margin-top: 4px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
        .notice { padding: 12px; background: #eef8ee; margin: 16px 0; }
        .errors { padding: 12px; background: #fff0f0; margin: 16px 0; }
        button { padding: 11px 18px; cursor: pointer; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <h1>King Kad Kahwin</h1>
    <p><strong>Order ID:</strong> {{ $order->order_id }}</p>
    <p><strong>Status:</strong> {{ $order->status }}</p>
    <p><strong>Pakej:</strong> {{ $order->package_count }}</p>

    @if (session('draft_saved'))
        <div class="notice">Draft berjaya disimpan. Anda boleh keluar dan sambung semula melalui link dashboard yang sama.</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Sila semak maklumat berikut:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php($couple = $order->couples->firstWhere('couple_number', 1))

    <form method="POST" action="{{ route('orders.draft.update', ['orderId' => $order->order_id, 'token' => $plainToken]) }}">
        @csrf

        <fieldset>
            <legend>Maklumat Pasangan</legend>
            <div class="grid">
                <div>
                    <label>Nama Pengantin Lelaki</label>
                    <input name="couple[groom_name]" value="{{ old('couple.groom_name', $couple?->groom_name) }}">
                </div>
                <div>
                    <label>Singkatan Pengantin Lelaki</label>
                    <input name="couple[groom_abbreviation]" value="{{ old('couple.groom_abbreviation', $couple?->groom_abbreviation) }}">
                </div>
                <div>
                    <label>Nama Pengantin Perempuan</label>
                    <input name="couple[bride_name]" value="{{ old('couple.bride_name', $couple?->bride_name) }}">
                </div>
                <div>
                    <label>Singkatan Pengantin Perempuan</label>
                    <input name="couple[bride_abbreviation]" value="{{ old('couple.bride_abbreviation', $couple?->bride_abbreviation) }}">
                </div>
            </div>
        </fieldset>

        @foreach ($order->packageSides->sortBy('side') as $packageSide)
            @php($side = $packageSide->side)
            @php($event = $packageSide->event)
            <fieldset>
                <legend>Pakej {{ ucfirst(strtolower($side)) }}</legend>

                <h3>Design</h3>
                <div class="grid">
                    <div>
                        <label>Tema</label>
                        <input name="sides[{{ $side }}][design][theme]" value="{{ old("sides.$side.design.theme", $packageSide->design?->theme) }}">
                    </div>
                    <div>
                        <label>Kod Design</label>
                        <input name="sides[{{ $side }}][design][design_code]" value="{{ old("sides.$side.design.design_code", $packageSide->design?->design_code) }}">
                    </div>
                    <div>
                        <label>Tajuk Kad</label>
                        <input name="sides[{{ $side }}][design][card_title]" value="{{ old("sides.$side.design.card_title", $packageSide->design?->card_title) }}">
                    </div>
                </div>

                <h3>Ibu Bapa</h3>
                <div class="grid">
                    <div>
                        <label>Nama Bapa</label>
                        <input name="sides[{{ $side }}][parents][father_name]" value="{{ old("sides.$side.parents.father_name", $packageSide->parents?->father_name) }}">
                    </div>
                    <div>
                        <label>Nama Ibu</label>
                        <input name="sides[{{ $side }}][parents][mother_name]" value="{{ old("sides.$side.parents.mother_name", $packageSide->parents?->mother_name) }}">
                    </div>
                </div>

                <h3>Majlis</h3>
                <div class="grid">
                    <div><label>Hari</label><input name="sides[{{ $side }}][event][day_name]" value="{{ old("sides.$side.event.day_name", $event?->day_name) }}"></div>
                    <div><label>Tarikh</label><input type="date" name="sides[{{ $side }}][event][event_date]" value="{{ old("sides.$side.event.event_date", $event?->event_date?->format('Y-m-d')) }}"></div>
                    <div><label>Tarikh Hijri</label><input name="sides[{{ $side }}][event][hijri_date]" value="{{ old("sides.$side.event.hijri_date", $event?->hijri_date) }}"></div>
                    <div><label>Masa Makan</label><input type="time" name="sides[{{ $side }}][event][meal_time]" value="{{ old("sides.$side.event.meal_time", $event?->meal_time ? substr($event->meal_time, 0, 5) : '') }}"></div>
                    <div><label>Masa Bersanding</label><input type="time" name="sides[{{ $side }}][event][bersanding_time]" value="{{ old("sides.$side.event.bersanding_time", $event?->bersanding_time ? substr($event->bersanding_time, 0, 5) : '') }}"></div>
                    <div><label>Nama Tempat</label><input name="sides[{{ $side }}][event][venue_name]" value="{{ old("sides.$side.event.venue_name", $event?->venue_name) }}"></div>
                </div>
                <label>Alamat Penuh</label>
                <textarea rows="4" name="sides[{{ $side }}][event][full_address]">{{ old("sides.$side.event.full_address", $event?->full_address) }}</textarea>
                <label>Google Maps URL</label>
                <input type="url" name="sides[{{ $side }}][event][google_maps_url]" value="{{ old("sides.$side.event.google_maps_url", $event?->google_maps_url) }}">

                <h3>Contact Person</h3>
                @foreach ($event?->contacts?->sortBy('contact_number') ?? [] as $contact)
                    <div class="grid">
                        <div>
                            <label>Contact {{ $contact->contact_number }} - Nama</label>
                            <input name="sides[{{ $side }}][event][contacts][{{ $contact->contact_number }}][contact_name]" value="{{ old("sides.$side.event.contacts.{$contact->contact_number}.contact_name", $contact->contact_name) }}">
                        </div>
                        <div>
                            <label>Contact {{ $contact->contact_number }} - Telefon</label>
                            <input name="sides[{{ $side }}][event][contacts][{{ $contact->contact_number }}][contact_phone]" value="{{ old("sides.$side.event.contacts.{$contact->contact_number}.contact_phone", $contact->contact_phone) }}">
                        </div>
                    </div>
                @endforeach
            </fieldset>
        @endforeach

        <fieldset>
            <legend>Penghantaran / Pickup</legend>
            <label>Kaedah</label>
            <select name="fulfilment[method]">
                <option value="">-- Pilih --</option>
                <option value="COURIER" @selected(old('fulfilment.method', $order->fulfilment?->method) === 'COURIER')>Courier</option>
                <option value="PICKUP" @selected(old('fulfilment.method', $order->fulfilment?->method) === 'PICKUP')>Self Pickup</option>
            </select>
            <label>Nama Penerima (Courier)</label>
            <input name="fulfilment[recipient_name]" value="{{ old('fulfilment.recipient_name', $order->fulfilment?->recipient_name) }}">
            <label>Telefon Penerima (Courier)</label>
            <input name="fulfilment[recipient_phone]" value="{{ old('fulfilment.recipient_phone', $order->fulfilment?->recipient_phone) }}">
            <label>Alamat Penghantaran (Courier)</label>
            <textarea rows="4" name="fulfilment[shipping_address]">{{ old('fulfilment.shipping_address', $order->fulfilment?->shipping_address) }}</textarea>
        </fieldset>

        <button type="submit">Simpan Draft</button>
    </form>
</main>
</body>
</html>
