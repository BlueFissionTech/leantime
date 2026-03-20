@extends($layout)

@section('content')
<div class="support-form-shell">
    <div class="support-panel">
        <h1>Create your {{ $portal['brandName'] }} Support account</h1>
        <p>This account is only for {{ $portal['productName'] }} support tracking.</p>
        <form method="post" action="{{ BASE_URL }}/support/register" class="support-form">
            <label>
                <span>First name</span>
                <input type="text" name="firstName" required />
            </label>
            <label>
                <span>Last name</span>
                <input type="text" name="lastName" />
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" required />
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required minlength="8" />
            </label>
            <button type="submit" class="support-button primary">Create Account</button>
        </form>
        <p class="support-footnote">
            Already registered?
            <a href="{{ BASE_URL }}/support/login">Sign in here</a>
        </p>
    </div>
</div>
@endsection
