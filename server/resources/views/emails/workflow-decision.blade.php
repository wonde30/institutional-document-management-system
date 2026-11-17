@extends('emails.layout')

@php
    $decLower = strtolower($decision ?? 'pending');
    $isApproved = in_array($decLower, ['approve', 'approved']);
    $isRejected = in_array($decLower, ['reject', 'rejected']);
    
    $bannerBg = $isApproved ? '#ecfdf5' : ($isRejected ? '#fef2f2' : '#fffbeb');
    $bannerBorder = $isApproved ? '#a7f3d0' : ($isRejected ? '#fecaca' : '#fde68a');
    $bannerColor = $isApproved ? '#047857' : ($isRejected ? '#b91c1c' : '#b45309');
    $dotColor = $isApproved ? '#10b981' : ($isRejected ? '#ef4444' : '#f59e0b');
    $buttonBg = $isApproved ? '#047857' : ($isRejected ? '#b91c1c' : '#0075de');
@endphp

@section('preheader', 'Workflow Decision: ' . ucfirst($decision) . ' on ' . ($ref ?? 'Document'))

@section('status_bar')
<tr>
    <td style="background-color: {{ $bannerBg }}; border-bottom: 1px solid {{ $bannerBorder }}; padding: 10px 28px; font-size: 13px; font-weight: 600; color: {{ $bannerColor }};">
        <span style="display: inline-block; width: 8px; height: 8px; background-color: {{ $dotColor }}; border-radius: 50%; margin-right: 6px;"></span>
        Workflow Decision Notice &bull; Status: {{ strtoupper($decision) }}
    </td>
</tr>
@endsection

@section('content')
<p style="margin: 0 0 16px 0; font-size: 15px; color: #1e293b;">
    Dear <strong>{{ $recipient->name }}</strong>,
</p>

<p style="margin: 0 0 20px 0; font-size: 14px; color: #334155;">
    An official workflow evaluation has concluded with a decision of <strong>{{ ucfirst($decision) }}</strong> for your document workflow in WU-DMS.
</p>

<!-- Metadata Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 24px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; width: 140px; background-color: #f1f5f9;">
            Final Decision:
        </td>
        <td style="padding: 10px 14px; font-size: 13px;">
            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; background-color: {{ $bannerBg }}; color: {{ $bannerColor }}; border: 1px solid {{ $bannerBorder }};">
                {{ $decision }}
            </span>
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Decided By:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $decidedBy }}
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Document Ref:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #0075de; font-family: monospace;">
            {{ $ref }}
        </td>
    </tr>
    <tr>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Document Title:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $title }}
        </td>
    </tr>
</table>

<!-- Comments Block -->
<div style="background-color: #f8fafc; border-left: 4px solid {{ $bannerColor }}; border-radius: 0 6px 6px 0; padding: 14px 18px; margin-bottom: 24px; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
    <div style="font-size: 12px; font-weight: 700; color: {{ $bannerColor }}; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
        Evaluator Remarks / Justification:
    </div>
    <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap;">{{ $comments }}</div>
</div>

<!-- CTA Button -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
    <tr>
        <td align="center">
            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; background-color: {{ $buttonBg }}; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                View Full Workflow Record &rarr;
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
    If the button does not work, copy and paste this URL into your browser:<br>
    <a href="{{ $actionUrl }}" style="color: #0075de; word-break: break-all;">{{ $actionUrl }}</a>
</p>
@endsection
