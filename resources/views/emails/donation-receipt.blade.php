@extends('emails.partials.layout', ['title' => 'Donation receipt'])

@section('content')
<p>Hello {{ $donation->donor_name }},</p>
<p>Thank you. This is your acknowledgement/receipt for church giving.</p>
<p><strong>Reference:</strong> {{ $donation->uuid }}<br>
<strong>Amount:</strong> PHP {{ number_format((float) $donation->amount, 2) }}<br>
<strong>Category:</strong> {{ $donation->category }}<br>
<strong>Giving method:</strong> {{ str_replace('_', ' ', $donation->giving_method) }}<br>
<strong>Status:</strong> {{ str_replace('_', ' ', $donation->status) }}<br>
<strong>Date/time:</strong> {{ optional($donation->paid_at)->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}</p>
<p>Scripture Alone Baptist Church appreciates your faithful giving.</p>
@endsection
