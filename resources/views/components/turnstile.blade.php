@props(['action'])
@if(app(\App\Services\Turnstile::class)->enabled($action))
    <div class="auth-turnstile" x-data="turnstileWidget(@js(['siteKey'=>app(\App\Services\SiteSettings::class)->get('turnstile_site_key'),'action'=>$action]))" x-on:turnstile-reset.window="render()">
        <div x-ref="widget"></div>
        <input type="hidden" name="cf-turnstile-response" x-bind:value="token">
        <div class="auth-turnstile__status"><span role="status" x-text="message">请加载人机验证。</span><button type="button" x-on:click="render()">重新验证</button></div>
        @error('turnstile')<p class="mt-2 text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
    </div>
@endif
