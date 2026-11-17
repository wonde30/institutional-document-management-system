WOLLO UNIVERSITY - ADMINISTRATIVE & DOCUMENT MANAGEMENT SYSTEM
ወሎ ዩኒቨርሲቲ - የአስተዳደር እና የሰነድ አስተዳደር ሥርዓት
=============================================================================

SLA ESCALATION & OVERDUE ACTION NOTICE
Status: {{ strtoupper($escalationLevel) }}

Dear {{ $recipient->name }},

An assigned institutional action item has exceeded its established deadline SLA.

DETAILS:
- Item Type: {{ $itemTypeName }}
- Reference Number: {{ $referenceNumber }}
- Title: {{ $title }}
- Original Deadline: {{ $dueDate }} (OVERDUE)

ACTION REQUIRED:
Please process this overdue item immediately to maintain compliance with
Wollo University SLA and accountability standards.

ACCESS URL:
{{ $actionUrl }}

-----------------------------------------------------------------------------
CONFIDENTIALITY NOTICE:
This electronic transmission contains privileged information intended solely
for authorized Wollo University personnel. Unauthorized use is prohibited.
Wollo University, P.O. Box 1145, Dessie, Ethiopia.
