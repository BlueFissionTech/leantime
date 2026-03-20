@once
@push('styles')
<link rel="stylesheet" href="{{ BASE_URL }}/dist/css/supportcenter.{{ $appSettings->appVersion ?? '3.7.3' }}.min.css" />
@endpush
@endonce
