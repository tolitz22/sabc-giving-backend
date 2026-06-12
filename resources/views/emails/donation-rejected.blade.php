@extends('emails.partials.layout', [
    'title' => 'Giving verification needs attention',
    'preheader' => 'We need your help verifying a recent bank transfer submission.',
])

@section('content')
<div style="padding:30px 28px 10px;">
    <div style="display:inline-block;padding:7px 12px;border-radius:999px;background:#ffe8e8;color:#9b1c1c;font-size:12px;font-weight:bold;letter-spacing:.4px;text-transform:uppercase;">
        Verification needed
    </div>
    <h1 style="margin:18px 0 10px;color:#123a5d;font-size:26px;line-height:1.2;">
        We need help verifying your bank transfer.
    </h1>
    <p style="margin:0;color:#52667a;font-size:16px;line-height:1.7;">
        Hello {{ $donation->donor_name }}, we could not verify the submitted bank transfer for the giving record below.
    </p>
</div>

<div style="padding:18px 28px 28px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d9e3ec;border-radius:14px;overflow:hidden;">
        <tr>
            <td style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#52667a;">Amount</td>
            <td align="right" style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#123a5d;font-weight:bold;">PHP {{ number_format((float) $donation->amount, 2) }}</td>
        </tr>
        <tr>
            <td style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#52667a;">Directed to</td>
            <td align="right" style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#123a5d;font-weight:bold;">{{ $donation->category }}</td>
        </tr>
        <tr>
            <td style="padding:14px 18px;color:#52667a;">Reference</td>
            <td align="right" style="padding:14px 18px;color:#123a5d;font-weight:bold;">{{ $donation->uuid }}</td>
        </tr>
    </table>
</div>

<div style="margin:0 28px 28px;padding:18px;border-radius:14px;background:#fff6f6;border:1px solid #ffd3d3;color:#7f1d1d;">
    <strong>Reason:</strong> {{ $donation->rejected_reason }}
</div>

<div style="padding:0 28px 32px;color:#52667a;line-height:1.7;">
    <p style="margin:0;">Please contact the church office or submit the correct proof of transfer if needed.</p>
</div>
@endsection
