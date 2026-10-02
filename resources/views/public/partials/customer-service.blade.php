@php
    $customerServiceBaseUrl = rtrim((string) config('kingkadkahwin.social.whatsapp'), '/');
    $customerServiceOrderId = $order->order_id ?? $orderId ?? null;
    $customerServiceMessage = $customerServiceOrderId
        ? "Salam King Kad Kahwin, saya perlukan bantuan untuk tempahan {$customerServiceOrderId}."
        : 'Salam King Kad Kahwin, saya ingin mendapatkan bantuan mengenai tempahan kad kahwin.';
    $customerServiceSeparator = str_contains($customerServiceBaseUrl, '?') ? '&' : '?';
    $customerServiceUrl = $customerServiceBaseUrl.$customerServiceSeparator.'text='.rawurlencode($customerServiceMessage);
@endphp

<a
    class="customer-service-button"
    href="{{ $customerServiceUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Hubungi Khidmat Pelanggan melalui WhatsApp"
>
    <span class="customer-service-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" role="img">
            <path d="M12 2a9.7 9.7 0 0 0-8.4 14.55L2.3 21.7l5.28-1.25A9.7 9.7 0 1 0 12 2Zm0 17.45a7.7 7.7 0 0 1-3.93-1.08l-.38-.22-3.12.74.78-3.04-.25-.4A7.7 7.7 0 1 1 12 19.45Zm4.23-5.76c-.23-.12-1.37-.68-1.58-.75-.21-.08-.37-.12-.52.11-.15.23-.6.75-.73.9-.14.16-.27.18-.5.06-.23-.11-.98-.36-1.87-1.15a7.02 7.02 0 0 1-1.3-1.62c-.13-.23-.01-.35.1-.47.1-.1.23-.27.35-.4.11-.14.15-.24.23-.4.08-.15.04-.29-.02-.4-.06-.12-.52-1.26-.72-1.72-.19-.46-.38-.4-.52-.4h-.44c-.15 0-.4.06-.6.29-.21.23-.8.78-.8 1.9s.82 2.2.93 2.36c.12.15 1.6 2.44 3.88 3.43.54.23.97.37 1.3.48.54.17 1.04.15 1.43.09.44-.07 1.37-.56 1.56-1.1.2-.55.2-1.02.14-1.11-.06-.1-.21-.15-.44-.27Z"/>
        </svg>
    </span>
    <span class="customer-service-copy">
        <strong>Khidmat Pelanggan</strong>
        <small>Kami sedia membantu</small>
    </span>
</a>
