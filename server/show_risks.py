import json

with open(r'c:\Users\IT3\Desktop\DMS\server\models_complete_audit.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

for c in ['Document', 'LegacyTransitionToken', 'OrganizationUnit', 'User']:
    m = data[c]
    print(f"=== {c} ===")
    for r in m['n1_risks']:
        print(f"  [{r.get('type')}] {r.get('method')}: {r.get('risk')}")

print("\n=== Models with boot/events ===")
for c in ['AssignmentParticipant', 'AssignmentResponse', 'AuditEvent', 'DocumentTextExtraction', 'DocumentVersion', 'TemplateVersion', 'WorkflowInstance', 'WorkflowTask', 'WorkflowTaskParticipant', 'WorkflowVersion']:
    m = data[c]
    print(f"{c}: boots={m['boot_methods']}, events={m['events']}")

print("\n=== Models with scopes ===")
for c, m in data.items():
    if m['scopes']:
        print(f"{c}: {m['scopes']}")
