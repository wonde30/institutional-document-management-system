import json

with open(r'c:\Users\IT3\Desktop\DMS\server\models_analysis.json', 'r', encoding='utf-8') as f:
    py_data = json.load(f)

with open(r'c:\Users\IT3\Desktop\DMS\server\models_php_verified.json', 'r', encoding='utf-8') as f:
    php_data = json.load(f)

print(f"Py models count: {len(py_data)}, PHP models count: {len(php_data)}")

# Let's inspect traits and special patterns
models_with_soft_deletes = []
models_with_composite_pk = []
models_with_lock_version = []
models_with_guards_version = []
models_with_eager_with = []
models_with_boot_events = []
models_with_n1_risks = []

for m in py_data:
    cname = m['class_name']
    php_m = php_data.get(cname, {})

    # Check soft deletes
    php_traits = php_m.get('traits', [])
    uses_sd = m['uses_soft_deletes'] or any('SoftDeletes' in t for t in php_traits)
    if uses_sd:
        models_with_soft_deletes.append(cname)

    # Check composite pk
    if m['uses_composite_key'] or any('HasCompositePrimaryKey' in t for t in php_traits) or m['composite_key']:
        models_with_composite_pk.append((cname, m['composite_key']))

    # Check guards version
    if m['uses_guards_version'] or any('GuardsVersionImmutability' in t for t in php_traits):
        models_with_guards_version.append(cname)

    # Check lock version
    if m['has_lock_version']:
        models_with_lock_version.append((cname, m['lock_version_details']))

    # Check eager with
    php_with = php_m.get('with', [])
    w = list(set(m['eager_with'] + php_with))
    if w:
        models_with_eager_with.append((cname, w))

    # Boot events
    if m['events'] or m['boot_methods']:
        models_with_boot_events.append((cname, m['boot_methods'], m['events']))

    # N+1 risks
    if m['n1_risks']:
        models_with_n1_risks.append((cname, m['n1_risks']))

print("\n--- Summary Statistics ---")
print(f"SoftDeletes count: {len(models_with_soft_deletes)}")
print(f"Composite PK count: {len(models_with_composite_pk)}")
print(f"GuardsVersionImmutability count: {len(models_with_guards_version)}")
print(f"lock_version count: {len(models_with_lock_version)}")
print(f"Eager $with count: {len(models_with_eager_with)}")
print(f"Boot/events count: {len(models_with_boot_events)}")
print(f"N+1 risks count: {len(models_with_n1_risks)}")

print("\nModels with $with:")
for c, w in models_with_eager_with:
    print(f"  {c}: {w}")

print("\nModels with GuardsVersionImmutability:")
for c in models_with_guards_version:
    print(f"  {c}")

print("\nModels with Composite PK:")
for c, pk in models_with_composite_pk:
    print(f"  {c}: {pk}")

print("\nModels with N+1 risks:")
for c, risks in models_with_n1_risks:
    print(f"  {c}: {len(risks)} risky methods/accessors")
    for r in risks:
        print(f"    - [{r['type']}] {r['method']}: {r['risk']}")
