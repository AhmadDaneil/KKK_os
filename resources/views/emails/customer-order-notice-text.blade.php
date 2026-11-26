KING KAD KAHWIN

Salam {{ $customerName }},

{{ $title }}

{{ $bodyText }}

No. Tempahan: {{ $orderReference }}
@if ($statusLabel)
Kemas kini status: {{ $statusLabel }}
@endif
@if ($amount)
Baki bayaran: {{ $amount }}
@endif

{{ $actionLabel }}: {{ $actionUrl }}

Pautan ini akan meminta pengesahan nombor telefon bagi melindungi maklumat tempahan anda.
