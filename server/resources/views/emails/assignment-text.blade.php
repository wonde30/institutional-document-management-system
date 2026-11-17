WOLLO UNIVERSITY - ADMINISTRATIVE & DOCUMENT MANAGEMENT SYSTEM
ወሎ ዩኒቨርሲቲ - የአስተዳደር እና የሰነድ አስተዳደር ሥርዓት
=============================================================================

DOCUMENT ASSIGNMENT NOTICE
Directive: {{ $verb ?? 'New Assignment' }}

Dear {{ $recipient->name }},

You have received an administrative document assignment in WU-DMS.

DETAILS:
- Reference Number: {{ $ref }}
- Document Title: {{ $title }}
- Priority: {{ strtoupper($priority) }}
- Due Date: {{ $dueDate }}

INSTRUCTIONS:
{{ $instructions }}

ACCESS URL:
{{ $actionUrl }}

-----------------------------------------------------------------------------
CONFIDENTIALITY NOTICE:
This electronic transmission contains privileged information intended solely
for authorized Wollo University personnel. Unauthorized use is prohibited.
Wollo University, P.O. Box 1145, Dessie, Ethiopia.
