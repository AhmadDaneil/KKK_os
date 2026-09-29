<form class="language-toggle" method="POST" action="{{ route('language.update') }}">
    @csrf
    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'en' ? 'ms' : 'en' }}">
    <button type="submit" title="{{ app()->getLocale() === 'en' ? 'Tukar kepada Bahasa Melayu' : 'Switch to English' }}">
        <span aria-hidden="true">文</span>
        {{ app()->getLocale() === 'en' ? 'Bahasa Melayu' : 'English' }}
    </button>
</form>
