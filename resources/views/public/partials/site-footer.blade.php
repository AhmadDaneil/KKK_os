<footer class="site-footer">
    <div class="footer-main">
        <div>
            <a class="brand footer-brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async"><b>KingKadKahwin</b></a>
            <p>Beautiful designs. A smooth process.</p>
        </div>

        <div class="footer-social">
            <p>Follow us</p>
            <div class="social-links">
                @foreach ([
                    ['key' => 'instagram', 'label' => 'Instagram'],
                    ['key' => 'facebook', 'label' => 'Facebook'],
                    ['key' => 'tiktok', 'label' => 'TikTok'],
                ] as $social)
                    @if (config('kingkadkahwin.social.'.$social['key']))
                        <a class="social-link social-{{ $social['key'] }}" href="{{ config('kingkadkahwin.social.'.$social['key']) }}" target="_blank" rel="noopener noreferrer" aria-label="KingKadKahwin on {{ $social['label'] }}">
                            <span aria-hidden="true">
                                @if ($social['key'] === 'instagram')
                                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" class="social-icon-fill"></circle></svg>
                                @elseif ($social['key'] === 'facebook')
                                    <svg viewBox="0 0 24 24"><path class="social-icon-fill" d="M14 8h3V4.3c-.5-.1-2.2-.3-4.1-.3C9 4 6.3 6.4 6.3 10.8V14H3v4h3.3v6h4V18h3.4l.6-4h-4v-2.8C10.3 9.3 10.8 8 14 8Z"></path></svg>
                                @else
                                    <svg viewBox="0 0 24 24"><path class="social-icon-fill" d="M14.5 3c.4 2.4 1.8 3.9 4.5 4.1v3.1a8.3 8.3 0 0 1-4.5-1.3v6.2a6.1 6.1 0 1 1-5.3-6v3.3a2.9 2.9 0 1 0 2.1 2.8V3h3.2Z"></path></svg>
                                @endif
                            </span>
                            {{ $social['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© {{ date('Y') }} KingKadKahwin</span>
        <span><a href="{{ route('public.privacy') }}">Privacy</a> · <a href="{{ route('public.terms') }}">Order Terms</a></span>
    </div>
</footer>
