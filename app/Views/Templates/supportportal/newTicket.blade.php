@extends($layout)

@section('content')
<div class="support-page">
    <div class="support-page-header">
        <div>
            <span class="support-eyebrow">{{ $portal['productName'] }}</span>
            <h1>New Support Ticket</h1>
        </div>
        <a class="support-button secondary" href="{{ $supportTicketsUrl }}">Back to Tickets</a>
    </div>

    <div class="support-panel">
        <form method="post" action="{{ $supportNewTicketUrl }}" class="support-form">
            <label>
                <span>Subject</span>
                <input type="text" name="headline" required />
            </label>
            <label>
                <span>Priority</span>
                <select name="priority">
                    @foreach($priorities as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Description</span>
                <textarea name="description" rows="10" required></textarea>
            </label>
            <button type="submit" class="support-button primary">Create Ticket</button>
        </form>
    </div>
</div>
@endsection
