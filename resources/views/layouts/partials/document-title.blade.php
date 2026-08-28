@php
    $pageTitle = isset($title)
        ? trim(strip_tags(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
        : '';
@endphp
<title>{{ $pageTitle !== '' ? $pageTitle.' · ' : '' }}{{ config('app.name', '课隙') }}</title>
