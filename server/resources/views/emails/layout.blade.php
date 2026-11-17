<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Wollo University DMS Notification' }}</title>
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100%;
            height: 100% !important;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans Ethiopic", "Nyala", sans-serif;
            color: #1e293b;
            line-height: 1.6;
        }
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .responsive-table {
                width: 100% !important;
            }
            .mobile-padding {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            .mobile-stack {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc;">
    <!-- Preheader for email clients -->
    <div style="display: none; font-size: 1px; color: #f8fafc; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
        @yield('preheader', 'Official notification from Wollo University Document Management System (WU-DMS)')
    </div>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 28px 12px 36px 12px;">
                <!-- Main Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);" class="email-container">
                    
                    <!-- Institutional Header Banner -->
                    <tr>
                        <td style="background-color: #1e3a8a; padding: 22px 28px; border-bottom: 3px solid #0075de;" class="mobile-padding">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 700; color: #93c5fd; letter-spacing: 0.5px; text-transform: uppercase;">
                                            ወሎ ዩኒቨርሲቲ &bull; Wollo University
                                        </div>
                                        <h1 style="margin: 4px 0 0 0; font-size: 19px; font-weight: 700; color: #ffffff; line-height: 1.3;">
                                            Administrative &amp; Document Management System
                                        </h1>
                                        <div style="font-size: 12px; color: #cbd5e1; margin-top: 3px;">
                                            Institutional Record &amp; Workflow Notification Engine
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Status Accent Bar -->
                    @hasSection('status_bar')
                        @yield('status_bar')
                    @endif

                    <!-- Content Body -->
                    <tr>
                        <td style="padding: 28px 28px 24px 28px; background-color: #ffffff;" class="mobile-padding">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer & Confidentiality -->
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 22px 28px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; line-height: 1.5;" class="mobile-padding">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #475569;">
                                            Wollo University &bull; P.O. Box 1145, Dessie, Ethiopia
                                        </p>
                                        <p style="margin: 0 0 8px 0; font-size: 11px; color: #64748b;">
                                            <strong>Institutional Confidentiality Notice:</strong> This electronic transmission contains privileged information intended solely for authorized Wollo University personnel. Any unauthorized review, dissemination, distribution, or copying is strictly prohibited.
                                        </p>
                                        <p style="margin: 0; font-size: 11px; color: #64748b;">
                                            <strong>የሚስጥራዊነት ማሳሰቢያ:</strong> ይህ ኤሌክትሮኒክ ማሳወቂያ ለተፈቀደለት የወሎ ዩኒቨርሲቲ ሰራተኛ ብቻ የተላከ ሚስጥራዊ የሥራ መረጃ ነው። ያለፈቃድ ማሰራጨት በጥብቅ የተከለከለ ነው።
                                        </p>
                                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #cbd5e1; font-size: 11px; color: #94a3b8; text-align: center;">
                                            WU-DMS Automated Notification System &bull; Please do not reply directly to this email.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
