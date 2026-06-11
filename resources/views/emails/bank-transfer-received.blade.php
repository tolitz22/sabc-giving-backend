@extends('emails.partials.layout', ['title' => 'Bank transfer received'])

@section('content')
<p>Hello {{ $donation->donor_name }},</p>
<p>We received your bank transfer details and proof of transfer. An admin will verify the transfer before it is marked paid.</p>
<p><strong>Reference:</strong> {{ $donation->uuid }}<br>
<strong>Amount:</strong> PHP {{ number_format((float) $donation->amount, 2) }}<br>
<strong>Category:</strong> {{ $donation->category }}<br>
<strong>Status:</strong> {{ str_replace('_', ' ', $donation->status) }}<br>
<strong>Date/time:</strong> {{ $donation->created_at->format('M j, Y g:i A') }}</p>
<p>This is an acknowledgement for church giving submitted through Scripture Alone Baptist Church Online Giving.</p>
@endsection
