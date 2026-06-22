<footer class="doc-footer">
    <strong>{{ $branding['footer_text_ar'] }}</strong>
    @if($branding['template_language'] === 'bilingual')<span> | {{ $branding['footer_text_en'] }}</span>@endif
    @if($branding['phone'])<span> | {{ $branding['phone'] }}</span>@endif
</footer>
