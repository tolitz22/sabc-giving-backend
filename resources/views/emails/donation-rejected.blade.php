@extends('emails.partials.layout', ['title' => 'Donation verification issue'])

@section('content')
<p>Hello {{ $donation->donor_name }},</p>
<p>We could not verify the submitted bank transfer for the donation below.</p>
<p><strong>Reference:</strong> {{ $donation->uuid }}<br>
<strong>Amount:</strong> PHP {{ number_format((float) $donation->amount, 2) }}<br>
<strong>Category:</strong> {{ $donation->category }}<br>
<strong>Status:</strong> {{ str_replace('_', ' ', $donation->status) }}</p>
<p><strong>Reason:</strong> {{ $donation->rejected_reason }}</p>
<p>Please contact the church office or submit the correct proof of transfer if needed.</p>
@endsection
