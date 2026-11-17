@extends('emails.layout')

@php
    $isEscalated = ($escalationLevel === 'escalated');
    $statusText = $isEscalated ? 'SLA ESCALATION ALERT' : 'OVERDUE ACTION NOTICE';
    $bannerColor = '#b91c1c';
    $bannerBg = '#fef2f2';
    $bannerBorder = '#fecaca';
@endphp

@section('preheader', 'Urgent: ' . $statusText . ' for ' . ($referenceNumber ?? 'Administrative Item'))

@section('status_bar')
<tr>
    <td style="background-color: {{ $bannerBg }}; border-bottom: 1px solid {{ $bannerBorder }}; padding: 10px 28px; font-size: 13px; font-weight: 700; color: {{ $bannerColor }};">
        <span style="display: inline-block; width: 8px; height: 8px; background-color: #ef4444; border-radius: 50%; margin-right: 6px;"></span>
        {{ $statusText }} &bull; Compliance Attention Required
    </td>
</tr>
@endsection

@section('content')
<p style="margin: 0 0 16px 0; font-size: 15px; color: #1e293b;">
    Dear <strong>{{ $recipient->name }}</strong>,
</p>

<p style="margin: 0 0 20px 0; font-size: 14px; color: #334155;">
    An assigned institutional action item has exceeded its established deadline SLA and requires immediate processing to prevent workflow stalling or institutional non-compliance.
</p>

<!-- Metadata Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 24px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; width: 140px; background-color: #f1f5f9;">
            Item Type:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #0f172a;">
            {{ $itemTypeName }}
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Reference No:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #b91c1c; font-family: monospace;">
            {{ $referenceNumber }}
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Item Title:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $title }}
        </td>
    </tr>
    <tr>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Original Deadline:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #b91c1c;">
            {{ $dueDate }} (Overdue)
        </td>
    </tr>
</table>

<!-- Urgency Alert Box -->
<div style="background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 0 6px 6px 0; padding: 14px 18px; margin-bottom: 24px; border-top: 1px solid #fee2e2; border-right: 1px solid #fee2e2; border-bottom: 1px solid #fee2e2;">
    <div style="font-size: 12px; font-weight: 700; color: #b91c1c; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
        Institutional Compliance Advisory:
    </div>
    <div style="font-size: 13px; color: #7f1d1d; line-height: 1.5;">
        In accordance with Wollo University Administrative Standards, unresolved items beyond the SLA grace period are subject to higher management review and administrative escalation.
    </div>
</div>

<!-- CTA Button -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
    <tr>
        <td align="center">
            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; background-color: #b91c1c; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                Process Overdue Item Now &rarr;
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
    If the button does not work, copy and paste this URL into your browser:<br>
    <a href="{{ $actionUrl }}" style="color: #b91c1c; word-break: break-all;">{{ $actionUrl }}</a>
</p>
@endsection
