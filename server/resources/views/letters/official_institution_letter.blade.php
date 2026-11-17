<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Official Letter' }}</title>
    <style>
        @font-face {
            font-family: 'Nyala';
            src: url('{{ $font_url }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @page {
            size: A4 portrait;
            margin: 32pt 40pt 45pt 40pt;
        }

        body {
            font-family: 'Nyala', 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        /* 3-Column Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .header-amharic {
            width: 38%;
            text-align: left;
            font-size: 10.5pt;
            line-height: 1.35;
        }

        .header-amharic .uni-am {
            font-size: 13pt;
            font-weight: bold;
            color: #002855;
        }

        .header-amharic .faculty-am {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1f2937;
        }

        .header-amharic .dept-am {
            font-size: 9.5pt;
            color: #1f2937;
        }

        .header-emblem {
            width: 24%;
            text-align: center;
        }

        .header-emblem .emblem-img {
            max-width: 76px;
            max-height: 76px;
            object-fit: contain;
        }

        .header-emblem .emblem-reserved-frame {
            width: 74px;
            height: 74px;
            margin: 0 auto;
            border: 1px dashed #94a3b8;
            border-radius: 50%;
            display: block;
            text-align: center;
            background-color: #f8fafc;
        }

        .header-emblem .emblem-reserved-text {
            font-size: 6.5pt;
            font-weight: bold;
            color: #64748b;
            line-height: 1.2;
            padding-top: 25px;
            font-family: 'DejaVu Sans', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-english {
            width: 38%;
            text-align: right;
            font-size: 10pt;
            line-height: 1.35;
        }

        .header-english .uni-en {
            font-size: 12pt;
            font-weight: bold;
            color: #002855;
        }

        .header-english .faculty-en {
            font-size: 9pt;
            font-weight: bold;
            color: #1f2937;
        }

        .header-english .dept-en {
            font-size: 9pt;
            color: #1f2937;
        }

        /* Header Horizontal Divider */
        .header-divider {
            border-bottom: 2.5px solid #002855;
            margin-top: 4px;
            margin-bottom: 12px;
            width: 100%;
        }

        /* Metadata Block (Ref No & Date) */
        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .metadata-table td {
            border: none;
            padding: 0;
        }

        .metadata-right {
            text-align: right;
            font-size: 11pt;
            line-height: 1.5;
        }

        .metadata-field {
            display: inline-block;
        }

        .metadata-value {
            font-weight: bold;
            text-decoration: underline;
            padding-left: 4px;
        }

        /* Recipient Block */
        .recipient-block {
            margin-bottom: 16px;
            font-size: 11.5pt;
            line-height: 1.45;
        }

        .recipient-arrow {
            font-weight: bold;
            margin-right: 4px;
        }

        .recipient-name {
            font-weight: bold;
        }

        .recipient-sub {
            padding-left: 20px;
        }

        /* Subject Line */
        .subject-block {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin: 18px 0;
            line-height: 1.4;
        }

        .subject-title {
            text-decoration: underline;
        }

        /* Main Letter Body */
        .letter-body {
            font-size: 11pt;
            line-height: 1.65;
            text-align: justify;
            margin-bottom: 20px;
        }

        .letter-body p {
            margin: 0 0 10px 0;
            text-indent: 28px;
        }

        /* Lower Sign-off & CC Section */
        .closing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .closing-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .cc-column {
            width: 54%;
            font-size: 9.5pt;
            line-height: 1.4;
        }

        .cc-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            font-size: 10pt;
        }

        .cc-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cc-item {
            margin-bottom: 3px;
        }

        .cc-bullet {
            font-size: 8pt;
            margin-right: 4px;
        }

        .cc-location {
            padding-left: 14px;
            margin-top: 3px;
        }

        .sign-column {
            width: 46%;
            text-align: right;
            font-size: 10.5pt;
            line-height: 1.35;
            position: relative;
        }

        .sign-greeting {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 11pt;
        }

        /* Blank signature line for physical execution */
        .blank-signature-area {
            height: 40px;
            margin-bottom: 4px;
            text-align: right;
        }

        .blank-signature-line {
            display: inline-block;
            width: 160px;
            border-bottom: 1px dotted #4b5563;
            margin-top: 32px;
        }

        /* Authentic Cryptographic Signature Badge (Only when digitally signed) */
        .digital-signature-badge {
            display: inline-block;
            text-align: left;
            border: 1px solid #0284c7;
            background-color: #f0f9ff;
            border-radius: 4px;
            padding: 4px 8px;
            margin-bottom: 6px;
            font-size: 7.5pt;
            color: #0369a1;
            font-family: 'DejaVu Sans', sans-serif;
            line-height: 1.25;
        }

        .digital-signature-badge .badge-title {
            font-weight: bold;
            color: #0284c7;
        }

        .sign-name {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 4px;
        }

        .sign-title-am {
            font-size: 10pt;
            font-weight: bold;
        }

        .sign-title-en {
            font-size: 9.5pt;
            color: #374151;
        }

        /* Approved Stamp Image Container */
        .stamp-container {
            margin-top: 8px;
            text-align: right;
        }

        .stamp-container img {
            max-width: 110px;
            max-height: 110px;
            object-fit: contain;
        }

        /* Clean reserved circular boundary for physical ink seal */
        .stamp-reserved-boundary {
            width: 95px;
            height: 95px;
            border: 1px dashed #94a3b8;
            border-radius: 50%;
            margin-top: 8px;
            margin-left: auto;
            text-align: center;
            background-color: #fcfcfc;
        }

        .stamp-reserved-text {
            font-size: 6pt;
            font-family: 'DejaVu Sans', sans-serif;
            color: #94a3b8;
            line-height: 1.2;
            padding-top: 36px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Bottom Fixed Footer */
        .footer-fixed {
            position: fixed;
            bottom: -20pt;
            left: 0;
            right: 0;
            height: 38pt;
            border-top: 1.5px solid #002855;
            padding-top: 5pt;
            font-size: 8.5pt;
            color: #374151;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .footer-contacts {
            text-align: left;
            width: 50%;
            line-height: 1.3;
        }

        .footer-instruction {
            text-align: right;
            width: 50%;
            line-height: 1.3;
            font-size: 8pt;
        }
    </style>
</head>
<body>

    <!-- 3-Column Institutional Letterhead -->
    <table class="header-table">
        <tr>
            <td class="header-amharic">
                <div class="uni-am">{{ $uni_am ?? 'ወሎ ዩኒቨርሲቲ' }}</div>
                <div class="faculty-am">{{ $faculty_am ?? 'ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት' }}</div>
                <div class="dept-am">{{ $dept_am ?? 'ኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል' }}</div>
                <div class="dept-am">{{ $location_am ?? 'ኮምቦልቻ' }}</div>
            </td>
            <td class="header-emblem">
                @if(!empty($emblem_data_uri))
                    <img src="{{ $emblem_data_uri }}" class="emblem-img" alt="Wollo University Emblem">
                @elseif(!empty($is_draft))
                    <div class="emblem-reserved-frame">
                        <div class="emblem-reserved-text">OFFICIAL EMBLEM<br>RESERVED</div>
                    </div>
                @else
                    <div class="emblem-reserved-frame" style="border: 1px dashed transparent; background-color: transparent;">
                        <!-- Blank emblem space reserved on final letter without placeholder text -->
                    </div>
                @endif
            </td>
            <td class="header-english">
                <div class="uni-en">{{ $uni_en ?? 'WOLLO UNIVERSITY' }}</div>
                <div class="faculty-en">{{ $faculty_en ?? 'KOMBOLCHA INSTITUTE OF TECHNOLOGY' }}</div>
                <div class="dept-en">{{ $dept_en ?? 'Information Technology Department' }}</div>
                <div class="dept-en">{{ $location_en ?? 'Kombolcha' }}</div>
            </td>
        </tr>
    </table>

    <div class="header-divider"></div>

    <!-- Metadata Row: Reference Number & Ethiopian Date -->
    <table class="metadata-table">
        <tr>
            <td></td>
            <td class="metadata-right">
                <div class="metadata-field">
                    <span>ቁጥር/Ref.No:-</span>
                    <span class="metadata-value">{{ $reference_number ?? '' }}</span>
                </div>
                <br>
                <div class="metadata-field">
                    <span>ቀን/Date:-</span>
                    <span class="metadata-value">{{ $ethiopian_date ?? '' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Recipient Block -->
    <div class="recipient-block">
        <div>
            <span class="recipient-arrow">&#10148;</span>
            <span class="recipient-name">{{ $recipient_lead ?? '' }}</span>
        </div>
        @if(!empty($recipient_sub))
            <div class="recipient-sub">({{ $recipient_sub }})</div>
        @endif
        @if(!empty($recipient_location))
            <div class="recipient-sub">{{ $recipient_location }}፤</div>
        @endif
    </div>

    <!-- Centered Subject -->
    <div class="subject-block">
        <span class="subject-title">ጉዳዩ፡- {{ $subject ?? '' }}፤</span>
    </div>

    <!-- Main Letter Content -->
    <div class="letter-body">
        {!! $body_html !!}
    </div>

    <!-- Lower Section: CC Distribution (Left) & Signatory + Stamp (Right) -->
    <table class="closing-table">
        <tr>
            <td class="cc-column">
                @if(!empty($cc_list) && count($cc_list) > 0)
                    <div class="cc-title">ግልባጭ፤</div>
                    <div class="cc-list">
                        @foreach($cc_list as $ccItem)
                            <div class="cc-item">
                                <span class="cc-bullet">&#10146;</span>
                                <span>{{ $ccItem }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($cc_location))
                        <div class="cc-location">{{ $cc_location }}</div>
                    @endif
                @endif
            </td>
            <td class="sign-column">
                <div class="sign-greeting">&laquo; ከሰላምታ ጋር &raquo;</div>

                @if(!empty($is_digitally_signed) && !empty($signature_details))
                    <div class="digital-signature-badge">
                        <div class="badge-title">&#128274; DIGITALLY SIGNED &amp; VERIFIED</div>
                        <div>Signer: {{ $signature_details['signer_name'] ?? $signatory_name }}</div>
                        <div>Time: {{ $signature_details['signed_at'] ?? '' }}</div>
                        <div>Digest: {{ substr($signature_details['digest'] ?? '', 0, 16) }}...</div>
                    </div>
                @else
                    <!-- Space reserved for physical signature -->
                    <div class="blank-signature-area">
                        <div class="blank-signature-line"></div>
                    </div>
                @endif

                <div class="sign-name">{{ $signatory_name ?? '' }}</div>
                @if(!empty($signatory_title_am))
                    <div class="sign-title-am">{{ $signatory_title_am }}</div>
                @endif
                @if(!empty($signatory_title_en))
                    <div class="sign-title-en">{{ $signatory_title_en }}</div>
                @endif

                @if(!empty($stamp_data_uri))
                    <div class="stamp-container">
                        <img src="{{ $stamp_data_uri }}" alt="Official Stamp">
                    </div>
                @elseif(!empty($is_draft))
                    <!-- Space reserved for physical departmental seal (draft preview) -->
                    <div class="stamp-reserved-boundary">
                        <div class="stamp-reserved-text">[OFFICIAL STAMP<br>RESERVED]</div>
                    </div>
                @else
                    <!-- Clean blank physical stamp area on final official letter -->
                    <div class="stamp-reserved-boundary" style="border: 1px dashed transparent; background-color: transparent;">
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Institutional Footer -->
    <div class="footer-fixed">
        <table class="footer-table">
            <tr>
                <td class="footer-contacts">
                    @if(!empty($footer_phone))
                        <div>&#9742; {{ $footer_phone }}</div>
                    @endif
                    @if(!empty($footer_fax))
                        <div>Fax: {{ $footer_fax }}</div>
                    @endif
                    @if(!empty($footer_pobox))
                        <div>&#9993; {{ $footer_pobox }}</div>
                    @endif
                </td>
                <td class="footer-instruction">
                    <span>{{ $footer_notice_am ?? 'መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ' }}</span><br>
                    <span style="font-size: 7.5pt; color: #4b5563;">{{ $footer_notice_en ?? 'please quote our ref. number when replying' }}</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
