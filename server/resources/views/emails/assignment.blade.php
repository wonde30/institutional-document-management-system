@extends('emails.layout')

@section('preheader', 'You have received an administrative document assignment: ' . ($ref ?? 'Document'))

@section('status_bar')
<tr>
    <td style="background-color: #e0f2fe; border-bottom: 1px solid #bae6fd; padding: 10px 28px; font-size: 13px; font-weight: 600; color: #0369a1;">
        <span style="display: inline-block; width: 8px; height: 8px; background-color: #0284c7; border-radius: 50%; margin-right: 6px;"></span>
        Operational Directive / Document Assignment
    </td>
</tr>
@endsection

@section('content')
<p style="margin: 0 0 16px 0; font-size: 15px; color: #1e293b;">
    Dear <strong>{{ $recipient->name }}</strong>,
</p>

<p style="margin: 0 0 20px 0; font-size: 14px; color: #334155;">
    You have been assigned an official administrative document for review and operational action within the Wollo University Document Management System.
</p>

<!-- Metadata Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 24px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; width: 130px; background-color: #f1f5f9;">
            Reference No:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #0f172a; font-family: monospace;">
            {{ $ref }}
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Document Title:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $title }}
        </td>
    </tr>
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Priority:
        </td>
        <td style="padding: 10px 14px; font-size: 13px;">
            @php
                $pColor = match(strtolower($priority ?? 'routine')) {
                    'urgent' => '#b91c1c',
                    'express' => '#c2410c',
                    default => '#0369a1'
                };
                $pBg = match(strtolower($priority ?? 'routine')) {
                    'urgent' => '#fee2e2',
                    'express' => '#ffedd5',
                    default => '#e0f2fe'
                };
            @endphp
            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; background-color: {{ $pBg }}; color: {{ $pColor }};">
                {{ $priority }}
            </span>
        </td>
    </tr>
    <tr>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Due Date:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $dueDate }}
        </td>
    </tr>
</table>

<!-- Directives / Instructions Block -->
<div style="background-color: #f8fafc; border-left: 4px solid #0075de; border-radius: 0 6px 6px 0; padding: 14px 18px; margin-bottom: 24px; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
    <div style="font-size: 12px; font-weight: 700; color: #0075de; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
        Action Instructions &amp; Remarks:
    </div>
    <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap;">{{ $instructions }}</div>
</div>

<!-- CTA Button -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
    <tr>
        <td align="center">
            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; background-color: #0075de; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                Open Document in WU-DMS Portal &rarr;
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
    If the button does not work, copy and paste this URL into your browser:<br>
    <a href="{{ $actionUrl }}" style="color: #0075de; word-break: break-all;">{{ $actionUrl }}</a>
</p>
@endsection
