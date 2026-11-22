import os
import glob
import re
import json

MODELS_DIR = r"c:\Users\IT3\Desktop\DMS\server\app\Models"

def analyze_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    filename = os.path.basename(filepath)
    class_name = os.path.splitext(filename)[0]

    # Check if abstract or interface or trait
    is_trait = "trait " + class_name in content
    is_interface = "interface " + class_name in content
    is_abstract = bool(re.search(r"abstract\s+class\s+" + class_name, content))

    # Table
    table_match = re.search(r"protected\s+\$table\s*=\s*['\"]([^'\"]+)['\"];", content)
    table = table_match.group(1) if table_match else None

    # Primary key
    pk_match = re.search(r"protected\s+\$primaryKey\s*=\s*['\"]([^'\"]+)['\"];", content)
    primary_key = pk_match.group(1) if pk_match else 'id'

    # Incrementing
    inc_match = re.search(r"(?:public|protected)\s+\$incrementing\s*=\s*(true|false);", content, re.IGNORECASE)
    if inc_match:
        incrementing = inc_match.group(1).lower() == 'true'
    else:
        # Check getIncrementing() method
        get_inc_match = re.search(r"function\s+getIncrementing\s*\(\)\s*:\s*bool\s*\{\s*return\s+(true|false);", content, re.IGNORECASE)
        if get_inc_match:
            incrementing = get_inc_match.group(1).lower() == 'true'
        else:
            incrementing = True

    # Key type
    kt_match = re.search(r"protected\s+\$keyType\s*=\s*['\"]([^'\"]+)['\"];", content)
    key_type = kt_match.group(1) if kt_match else 'int'

    # Traits
    traits = []
    # match `use HasFactory, SoftDeletes;` or individual `use SoftDeletes;` inside class body
    # find class definition
    class_body_match = re.search(r"class\s+" + class_name + r"[^{]*\{(.*)", content, re.DOTALL)
    class_body = class_body_match.group(1) if class_body_match else content

    # Find traits at top of class body before methods
    trait_uses = re.findall(r"^\s*use\s+([^;]+);", class_body, re.MULTILINE)
    for u in trait_uses:
        # exclude traits in anonymous classes or methods
        for t in u.split(','):
            t_clean = t.strip()
            if t_clean:
                traits.append(t_clean)

    uses_soft_deletes = any('SoftDeletes' in t for t in traits)
    uses_composite_key = any('HasCompositePrimaryKey' in t for t in traits)
    uses_guards_version = any('GuardsVersionImmutability' in t for t in traits)

    # Composite primary key columns
    composite_key = None
    if uses_composite_key or 'getCompositeKey' in class_body:
        comp_match = re.search(r"function\s+getCompositeKey\s*\(\)[^{]*\{\s*return\s*\[(.*?)\];", class_body, re.DOTALL)
        if comp_match:
            composite_key = [x.strip(" '\"\t\n\r") for x in comp_match.group(1).split(',') if x.strip(" '\"\t\n\r")]

    # $fillable
    fillable = []
    fillable_match = re.search(r"protected\s+\$fillable\s*=\s*\[(.*?)\];", class_body, re.DOTALL)
    if fillable_match:
        items = re.findall(r"['\"]([^'\"]+)['\"]", fillable_match.group(1))
        fillable = items

    # $guarded
    guarded = []
    guarded_match = re.search(r"protected\s+\$guarded\s*=\s*\[(.*?)\];", class_body, re.DOTALL)
    if guarded_match:
        items = re.findall(r"['\"]([^'\"]+)['\"]", guarded_match.group(1))
        guarded = items
    elif re.search(r"protected\s+\$guarded\s*=\s*\[\s*\];", class_body):
        guarded = []

    # $casts property
    casts = {}
    casts_prop_match = re.search(r"protected\s+\$casts\s*=\s*\[(.*?)\];", class_body, re.DOTALL)
    if casts_prop_match:
        prop_items = re.findall(r"['\"]([^'\"]+)['\"]\s*=>\s*['\"]?([^'\",\n\r]+)['\"]?", casts_prop_match.group(1))
        for k, v in prop_items:
            casts[k.strip()] = v.strip().strip("'\"")

    # casts() method (Laravel 11)
    casts_func_match = re.search(r"function\s+casts\s*\(\)\s*:\s*array\s*\{(.*?return\s*\[(.*?)\];.*?)\}", class_body, re.DOTALL)
    if casts_func_match:
        func_items = re.findall(r"['\"]([^'\"]+)['\"]\s*=>\s*['\"]?([^'\",\n\r]+)['\"]?", casts_func_match.group(2))
        for k, v in func_items:
            casts[k.strip()] = v.strip().strip("'\"")

    # $with (eager loading)
    eager_with = []
    with_match = re.search(r"protected\s+\$with\s*=\s*\[(.*?)\];", class_body, re.DOTALL)
    if with_match:
        eager_with = re.findall(r"['\"]([^'\"]+)['\"]", with_match.group(1))

    # Relationships
    relations = []
    # Match methods returning relationship
    # e.g. public function category(): BelongsTo { return $this->belongsTo(...); }
    # or without return type hint: public function category() { return $this->belongsTo(...); }
    rel_keywords = [
        'hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany',
        'hasOneThrough', 'hasManyThrough', 'morphToMany', 'morphedByMany'
    ]
    rel_pattern = re.compile(
        r"public\s+function\s+([a-zA-Z0-9_]+)\s*\([^\)]*\)\s*(?::\s*([a-zA-Z0-9_]+))?\s*\{([^}]+)\}",
        re.DOTALL
    )

    for m in rel_pattern.finditer(class_body):
        m_name = m.group(1)
        m_return_type = m.group(2)
        m_body = m.group(3)

        # Check if body contains $this->(rel_keyword)
        for rk in rel_keywords:
            rk_call = re.search(r"\$this\s*->\s*" + rk + r"\s*\((.*?)\)(?:\s*->|;)", m_body, re.DOTALL)
            if rk_call:
                args_str = rk_call.group(1)
                # extract args
                # first arg is usually Model::class or similar (except morphTo)
                args = [a.strip() for a in re.split(r",(?![^\(]*\))", args_str) if a.strip()]
                # clean up class name
                related_model = args[0] if len(args) > 0 else None
                if related_model:
                    related_model = related_model.replace('::class', '').strip('\\\'"')
                
                fk = args[1].strip('\'" ') if len(args) > 1 else None
                lk = args[2].strip('\'" ') if len(args) > 2 else None
                extra = [a.strip('\'" ') for a in args[3:]]

                # check for extra chain calls like withPivot, ofMany, orderBy, etc.
                chains = []
                for chain in re.findall(r"->([a-zA-Z0-9_]+)\s*\(([^\)]*)\)", m_body):
                    chains.append(f"{chain[0]}({chain[1]})")

                relations.append({
                    'name': m_name,
                    'type': rk,
                    'return_type': m_return_type,
                    'related_model': related_model,
                    'foreign_key': fk,
                    'local_key': lk,
                    'extra_args': extra,
                    'chains': chains
                })
                break

    # Scopes
    scopes = []
    scope_matches = re.finditer(r"public\s+function\s+scope([a-zA-Z0-9_]+)\s*\(", class_body)
    for sm in scope_matches:
        s_name = sm.group(1)
        scopes.append({
            'name': s_name[0].lower() + s_name[1:],
            'type': 'local',
            'method': 'scope' + s_name
        })

    # Global scopes in boot/booted
    if 'addGlobalScope' in class_body:
        for gsm in re.finditer(r"static::addGlobalScope\s*\(\s*['\"]([^'\"]+)['\"]", class_body):
            scopes.append({
                'name': gsm.group(1),
                'type': 'global'
            })
        for gsm in re.finditer(r"static::addGlobalScope\s*\(\s*new\s+([a-zA-Z0-9_]+)", class_body):
            scopes.append({
                'name': gsm.group(1),
                'type': 'global_class'
            })

    # boot() or booted() methods, model events
    boot_methods = []
    events = []
    boot_match = re.search(r"protected\s+static\s+function\s+(boot|booted)\s*\(\)\s*(?::\s*void)?\s*\{(.*?)\n\s*\}", class_body, re.DOTALL)
    if boot_match:
        boot_name = boot_match.group(1)
        boot_body = boot_match.group(2)
        boot_methods.append(boot_name)

        # find model events registered in boot/booted
        event_names = ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'replicating']
        for ev in event_names:
            if f"static::{ev}" in boot_body:
                events.append(ev)

    # Also check other static boot methods like bootGuardsVersionImmutability
    other_boots = re.findall(r"protected\s+static\s+function\s+(boot[a-zA-Z0-9_]+)\s*\(", class_body)
    for ob in other_boots:
        if ob not in boot_methods:
            boot_methods.append(ob)

    # Check $dispatchesEvents property
    dispatches = re.search(r"protected\s+\$dispatchesEvents\s*=\s*\[(.*?)\];", class_body, re.DOTALL)
    if dispatches:
        events.append(f"dispatchesEvents: {dispatches.group(1).strip()}")

    # lock_version / optimistic locking
    has_lock_version = ('lock_version' in fillable) or ('lock_version' in casts) or ('lock_version' in content)
    lock_version_details = []
    if 'lock_version' in fillable:
        lock_version_details.append('in $fillable')
    if 'lock_version' in casts:
        lock_version_details.append(f"in $casts ({casts.get('lock_version')})")
    if not lock_version_details and 'lock_version' in content:
        # find references
        lock_version_details.append('referenced in code')

    # Custom query methods / Accessors that might cause N+1 issues
    n1_risks = []
    # Check accessors: get[Name]Attribute
    accessor_matches = re.finditer(r"public\s+function\s+get([a-zA-Z0-9_]+)Attribute\s*\([^\)]*\)\s*\{([^}]+)\}", class_body)
    for am in accessor_matches:
        attr_name = am.group(1)
        body = am.group(2)
        # Check if accessor accesses relations or runs DB queries
        risky_terms = []
        if 'DB::' in body:
            risky_terms.append('DB:: query')
        # Check if accessing $this->relation
        for rel in relations:
            rname = rel['name']
            if re.search(r"\$this\s*->\s*" + rname + r"\b(?!\()", body):
                risky_terms.append(f"lazy-loads relation '${rname}'")
        if re.search(r"\b(all|get|first|find|where|count)\b", body) and 'DB::' not in risky_terms:
            risky_terms.append('query execution')
        if risky_terms:
            n1_risks.append({
                'type': 'accessor',
                'method': f"get{attr_name}Attribute",
                'risk': ", ".join(risky_terms),
                'snippet': body.strip().replace('\n', ' ')[:120]
            })

    # Check all methods for DB:: or lazy-loading inside methods
    method_pattern = re.compile(r"public\s+function\s+([a-zA-Z0-9_]+)\s*\([^\)]*\)\s*(?::\s*[a-zA-Z0-9_]+)?\s*\{([^}]+)\}", re.DOTALL)
    rel_names = {r['name'] for r in relations}
    for mp in method_pattern.finditer(class_body):
        mname = mp.group(1)
        mbody = mp.group(2)
        if mname in rel_names or mname.startswith('scope') or mname.startswith('get') and mname.endswith('Attribute'):
            continue
        
        risky = []
        if 'DB::' in mbody:
            risky.append('DB:: query')
        for rname in rel_names:
            if re.search(r"\$this\s*->\s*" + rname + r"\b(?!\()", mbody):
                risky.append(f"lazy-loads relation '${rname}'")
        if 'foreach' in mbody or 'while' in mbody:
            for rname in rel_names:
                if f"->{rname}" in mbody:
                    risky.append(f"loop over relation '${rname}'")

        if risky:
            n1_risks.append({
                'type': 'custom_method',
                'method': mname,
                'risk': ", ".join(set(risky)),
                'snippet': mbody.strip().replace('\n', ' ')[:120]
            })

    return {
        'class_name': class_name,
        'filename': filename,
        'table': table,
        'primary_key': primary_key,
        'composite_key': composite_key,
        'incrementing': incrementing,
        'key_type': key_type,
        'traits': traits,
        'uses_soft_deletes': uses_soft_deletes,
        'uses_composite_key': uses_composite_key,
        'uses_guards_version': uses_guards_version,
        'fillable': fillable,
        'guarded': guarded,
        'casts': casts,
        'eager_with': eager_with,
        'relations': relations,
        'scopes': scopes,
        'boot_methods': boot_methods,
        'events': events,
        'has_lock_version': has_lock_version,
        'lock_version_details': lock_version_details,
        'n1_risks': n1_risks
    }

def main():
    files = sorted(glob.glob(os.path.join(MODELS_DIR, "*.php")))
    results = []
    for f in files:
        results.append(analyze_file(f))

    with open(r"c:\Users\IT3\Desktop\DMS\server\models_analysis.json", "w", encoding="utf-8") as out:
        json.dump(results, out, indent=2)

    print(f"Successfully analyzed {len(results)} models.")

if __name__ == "__main__":
    main()
