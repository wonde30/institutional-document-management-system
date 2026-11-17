@extends('emails.layout')

@section('preheader', 'Action Required: Workflow Task on ' . ($ref ?? 'Workflow Document'))

@section('status_bar')
<tr>
    <td style="background-color: #ecfdf5; border-bottom: 1px solid #a7f3d0; padding: 10px 28px; font-size: 13px; font-weight: 600; color: #047857;">
        <span style="display: inline-block; width: 8px; height: 8px; background-color: #10b981; border-radius: 50%; margin-right: 6px;"></span>
        Workflow Decision Required &bull; Stage Evaluation
    </td>
</tr>
@endsection

@section('content')
<p style="margin: 0 0 16px 0; font-size: 15px; color: #1e293b;">
    Dear <strong>{{ $recipient->name }}</strong>,
</p>

<p style="margin: 0 0 20px 0; font-size: 14px; color: #334155;">
    An official workflow task has been assigned to you and is awaiting your institutional review and decision in WU-DMS.
</p>

<!-- Metadata Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 24px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; width: 140px; background-color: #f1f5f9;">
            Task Type:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 700; color: #0f172a;">
            {{ $taskType }}{{ $branchInfo }}
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
    <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Document Title:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1e293b;">
            {{ $title }}
        </td>
    </tr>
    <tr>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #f1f5f9;">
            Due Date / SLA:
        </td>
        <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #b91c1c;">
            {{ $dueDate }}
        </td>
    </tr>
</table>

<!-- CTA Button -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
    <tr>
        <td align="center">
            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; background-color: #047857; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                Review &amp; Submit Decision &rarr;
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
    If the button does not work, copy and paste this URL into your browser:<br>
    <a href="{{ $actionUrl }}" style="color: #047857; word-break: break-all;">{{ $actionUrl }}</a>
</p>
@endsection
