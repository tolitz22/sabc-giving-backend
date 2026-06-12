@php
    $isBankTransfer = $donation->giving_method === \App\Constants\DonationOptions::METHOD_BANK_TRANSFER;
    $displayName = $donation->is_anonymous ? 'friend' : $donation->donor_name;
    $statusLabel = $isBankTransfer ? 'Received for review' : 'Received successfully';
    $methodLabel = ucwords(str_replace('_', ' ', $donation->giving_method));
    $displayTimezone = config('app.display_timezone', 'Asia/Manila');
    $submittedAt = $donation->created_at->copy()->timezone($displayTimezone)->format('M j, Y g:i A');
@endphp

@extends('emails.partials.layout', [
    'title' => 'Thank you for giving',
    'preheader' => 'Your giving has been received by Scripture Alone Baptist Church.',
])

@section('content')
<div style="padding:30px 28px 10px;">
    <div style="display:inline-block;padding:7px 12px;border-radius:999px;background:#fff4d6;color:#8a5c00;font-size:12px;font-weight:bold;letter-spacing:.4px;text-transform:uppercase;">
        {{ $statusLabel }}
    </div>
    <h1 style="margin:18px 0 10px;color:#123a5d;font-size:28px;line-height:1.2;">
        Thank you for giving, {{ $displayName }}.
    </h1>
    <p style="margin:0;color:#52667a;font-size:16px;line-height:1.7;">
        Your generosity helps Scripture Alone Baptist Church continue serving the Lord through worship, discipleship, missions, and gospel ministry.
    </p>
</div>

<div style="padding:18px 28px 28px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d9e3ec;border-radius:14px;overflow:hidden;">
        <tr>
            <td style="padding:18px;background:#f6f9fc;border-bottom:1px solid #e4ebf2;">
                <div style="font-size:13px;color:#52667a;">Amount</div>
                <div style="margin-top:4px;font-size:26px;font-weight:bold;color:#123a5d;">PHP {{ number_format((float) $donation->amount, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#52667a;">Directed to</td>
                        <td align="right" style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#123a5d;font-weight:bold;">{{ $donation->category }}</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#52667a;">Giving method</td>
                        <td align="right" style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#123a5d;font-weight:bold;">{{ $methodLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#52667a;">Reference</td>
                        <td align="right" style="padding:14px 18px;border-bottom:1px solid #edf2f7;color:#123a5d;font-weight:bold;">{{ $donation->uuid }}</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 18px;color:#52667a;">Date submitted</td>
                        <td align="right" style="padding:14px 18px;color:#123a5d;font-weight:bold;">{{ $submittedAt }} PHT</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

@if ($isBankTransfer)
    <div style="margin:0 28px 28px;padding:18px;border-radius:14px;background:#edf7ef;border:1px solid #cfe8d5;color:#224b2d;">
        We received your bank transfer details and proof of transfer. Our team will review the submission. No additional email will be sent when it is verified unless the church needs to contact you.
    </div>
@else
    <div style="margin:0 28px 28px;padding:18px;border-radius:14px;background:#edf7ef;border:1px solid #cfe8d5;color:#224b2d;">
        Your payment has been confirmed. Thank you for partnering with the ministry of Scripture Alone Baptist Church.
    </div>
@endif

<div style="padding:0 28px 32px;color:#52667a;line-height:1.7;">
    <p style="margin:0 0 12px;">May the Lord use every gift for His glory and for the advancement of His Word.</p>
    <p style="margin:0;color:#123a5d;font-weight:bold;">Scripture Alone Baptist Church</p>
</div>
@endsection
