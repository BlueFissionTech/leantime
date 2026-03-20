@extends($layout)

@section('content')
<div class="support-shell">
    <div class="support-hero">
        <span class="support-eyebrow">{{ $portal['productName'] }}</span>
        <h1>Software support without the extra project-management surface.</h1>
        <p>Open an issue, track its status, and keep the comment thread in one place.</p>
        <div class="support-cta-row">
            <a class="support-button primary" href="{{ $supportRegisterUrl }}">Create Support Account</a>
            <a class="support-button secondary" href="{{ $supportLoginUrl }}">Sign In</a>
        </div>
    </div>

    <div class="support-card-grid">
        <section class="support-card">
            <h2>What you can do here</h2>
            <ul class="support-list">
                <li>Open a support ticket for {{ $portal['productName'] }}</li>
                <li>Review active and resolved tickets</li>
                <li>Comment directly on the issue thread</li>
            </ul>
        </section>
        <section class="support-card">
            <h2>What stays internal</h2>
            <ul class="support-list">
                <li>Engineering workflows and kanban stay with the internal support team</li>
                <li>You only see tickets tied to your account</li>
                <li>Internal project tools remain out of the portal</li>
            </ul>
        </section>
    </div>
</div>
@endsection
