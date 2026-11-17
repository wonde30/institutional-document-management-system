WOLLO UNIVERSITY - ADMINISTRATIVE & DOCUMENT MANAGEMENT SYSTEM
ወሎ ዩኒቨርሲቲ - የአስተዳደር እና የሰነድ አስተዳደር ሥርዓት
=============================================================================

WORKFLOW DECISION NOTICE
Decision: {{ strtoupper($decision) }}

Dear {{ $recipient->name }},

An official workflow evaluation has concluded with a decision of {{ strtoupper($decision) }}.

DETAILS:
- Decision: {{ strtoupper($decision) }}
- Decided By: {{ $decidedBy }}
- Document Reference: {{ $ref }}
- Document Title: {{ $title }}

EVALUATOR REMARKS:
{{ $comments }}

ACCESS URL:
{{ $actionUrl }}

-----------------------------------------------------------------------------
CONFIDENTIALITY NOTICE:
This electronic transmission contains privileged information intended solely
for authorized Wollo University personnel. Unauthorized use is prohibited.
Wollo University, P.O. Box 1145, Dessie, Ethiopia.
