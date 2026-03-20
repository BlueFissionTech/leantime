<!DOCTYPE html>
<html dir="{{ __('language.direction') }}" lang="{{ __('language.code') }}">
<head>
    <title>{{ $portalBrandName }}</title>
    <meta name="requestId" content="{{ \Illuminate\Support\Str::random(4) }}">
    <meta name="description" content="{{ $portalBrandName }}">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <meta name="identifier-URL" content="{{ $supportBaseUrl }}">
    <meta name="leantime-version" content="{{ $version ?? '' }}">

    <link rel="shortcut icon" href="{{ $supportAssetBaseUrl }}/dist/images/favicon.png"/>
    <link rel="apple-touch-icon" href="{{ $supportAssetBaseUrl }}/dist/images/apple-touch-icon.png">

    <link rel="stylesheet" href="{{ $supportAssetBaseUrl }}/dist/css/main.{{ $version ?? '' }}.min.css"/>
    <link rel="stylesheet" href="{{ $supportAssetBaseUrl }}/dist/css/app.{{ $version ?? '' }}.min.css"/>

    <script src="{{ $supportAssetBaseUrl }}/api/i18n?v={{ $version ?? '' }}"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-htmx.{{ $version ?? '' }}.min.js"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-htmx-extensions.{{ $version ?? '' }}.min.js"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-frameworks.{{ $version ?? '' }}.min.js"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-framework-plugins.{{ $version ?? '' }}.min.js"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-global-component.{{ $version ?? '' }}.min.js"></script>
    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-app.{{ $version ?? '' }}.min.js"></script>

    <style>
        :root {
            --accent1: {{ $primaryColor }};
            --accent2: {{ $secondaryColor }};
            --primary-font-family: 'Helvetica Neue', Helvetica, sans-serif;
        }
        body.support-portal-body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, {{ $secondaryColor }} 18%, white) 0, transparent 35%),
                linear-gradient(180deg, #f6f8fb 0%, #eef3f8 100%);
        }
        .support-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 24px;
            background: {{ $primaryColor }};
            color: #fff;
        }
        .support-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            color: inherit;
            text-decoration: none;
            font-weight: 700;
        }
        .support-brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: {{ $secondaryColor }};
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        .support-layout {
            max-width: 1080px;
            margin: 0 auto;
            padding: 32px 20px 56px;
        }
        .support-hero, .support-panel, .support-card, .support-ticket-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            box-shadow: 0 18px 55px rgba(15, 23, 42, 0.08);
        }
        .support-hero, .support-panel {
            padding: 28px;
        }
        .support-hero h1, .support-page-header h1 {
            margin: 0 0 10px;
            color: #132238;
        }
        .support-eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: {{ $secondaryColor }};
        }
        .support-card-grid, .support-ticket-list {
            display: grid;
            gap: 18px;
        }
        .support-card-grid {
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            margin-top: 22px;
        }
        .support-card {
            padding: 22px;
        }
        .support-list {
            margin: 0;
            padding-left: 18px;
        }
        .support-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        .support-form-shell {
            max-width: 560px;
            margin: 24px auto 0;
        }
        .support-form {
            display: grid;
            gap: 16px;
        }
        .support-form label span {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #132238;
        }
        .support-form input,
        .support-form select,
        .support-form textarea {
            width: 100%;
            border: 1px solid rgba(15, 23, 42, 0.14);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            background: #fff;
        }
        .support-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid transparent;
            font-weight: 700;
            cursor: pointer;
        }
        .support-button.primary {
            background: {{ $primaryColor }};
            color: #fff;
        }
        .support-button.secondary {
            background: #fff;
            color: {{ $primaryColor }};
            border-color: rgba(15, 23, 42, 0.12);
        }
        .support-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }
        .support-ticket-card {
            display: block;
            padding: 18px 20px;
            color: inherit;
            text-decoration: none;
        }
        .support-ticket-card.archived {
            opacity: 0.78;
        }
        .support-ticket-card-top,
        .support-ticket-meta,
        .support-comment-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .support-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, {{ $secondaryColor }} 14%, white);
            color: #132238;
            font-size: 12px;
            font-weight: 700;
        }
        .support-ticket-meta,
        .support-comment-meta,
        .support-footnote,
        .support-empty {
            color: #516173;
            font-size: 13px;
        }
        .support-ticket-section + .support-ticket-section {
            margin-top: 28px;
        }
        .support-richtext {
            line-height: 1.65;
            color: #1c2938;
            white-space: normal;
        }
        .support-comment-list {
            display: grid;
            gap: 14px;
            margin-bottom: 20px;
        }
        .support-comment {
            padding: 16px 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }
        .support-comment:first-child {
            border-top: 0;
            padding-top: 0;
        }
        @media (max-width: 720px) {
            .support-topbar,
            .support-page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .support-layout {
                padding: 20px 14px 40px;
            }
        }
    </style>
</head>
<body class="support-portal-body">
    <header class="support-topbar">
        <a href="{{ $supportHomeUrl }}" class="support-brand">
            @if(!empty($portalLogoUrl))
                <img src="{{ $portalLogoUrl }}" alt="{{ $portalBrandName }}" style="max-height:42px; max-width:140px;" />
            @else
                <span class="support-brand-mark">{{ \Illuminate\Support\Str::substr($portalBrandName, 0, 1) }}</span>
            @endif
            <span>{{ $portalBrandName }} Support</span>
        </a>

        @if(session()->exists('userdata.id'))
            <form method="post" action="{{ $supportLogoutUrl }}">
                <button type="submit" class="support-button secondary">Sign Out</button>
            </form>
        @endif
    </header>

    <main class="support-layout">
        {!! $tpl->displayNotification() !!}

        @isset($action, $module)
            @include("$module::$action")
        @else
            @yield('content')
        @endisset
    </main>

    <script src="{{ $supportAssetBaseUrl }}/dist/js/compiled-footer.{{ $version ?? '' }}.min.js"></script>
</body>
</html>
