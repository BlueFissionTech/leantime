@extends($layout)

@section('content')
<div class="support-form-shell">
    <div class="support-panel">
        <h1>Sign in to {{ $portal['brandName'] }} Support</h1>
        <p>Use the support account tied to your email.</p>
        <form method="post" action="{{ BASE_URL }}/support/login" class="support-form">
            <label>
                <span>Email</span>
                <input type="email" name="email" required />
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required />
            </label>
            <button type="submit" class="support-button primary">Sign In</button>
        </form>
        <p class="support-footnote">
            Need access?
            <a href="{{ BASE_URL }}/support/register">Create a support account</a>
        </p>
    </div>
</div>
@endsection
