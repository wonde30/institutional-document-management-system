<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$modelsDir = __DIR__ . '/app/Models';
$files = glob($modelsDir . '/*.php');
sort($files);

$results = [];

foreach ($files as $file) {
    $className = basename($file, '.php');
    $fqcn = "App\\Models\\{$className}";

    if (!class_exists($fqcn)) {
        continue;
    }

    $ref = new ReflectionClass($fqcn);
    if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
        continue;
    }

    $source = file_get_contents($file);

    try {
        $instance = new $fqcn();
    } catch (\Throwable $e) {
        $results[$className] = [
            'error' => $e->getMessage()
        ];
        continue;
    }

    // 1. Identifiers
    $table = $instance->getTable();
    $pk = $instance->getKeyName();
    $inc = $instance->getIncrementing();
    $keyType = $instance->getKeyType();

    // Composite primary key check
    $compositeKey = null;
    if (method_exists($instance, 'getCompositeKey')) {
        try {
            $compositeKey = $instance->getCompositeKey();
        } catch (\Throwable) {}
    }

    // 2. Fillable / Guarded
    $fillable = $instance->getFillable();
    $guarded = $instance->getGuarded();

    // 3. Casts
    $casts = $instance->getCasts();

    // 4. Traits
    $traits = array_values(class_uses_recursive($fqcn));
    $shortTraits = array_map(function($t) {
        $parts = explode('\\', $t);
        return end($parts);
    }, $traits);
    $usesSoftDeletes = in_array('SoftDeletes', $shortTraits, true);
    $usesGuardsVersion = in_array('GuardsVersionImmutability', $shortTraits, true);
    $usesCompositeKeyTrait = in_array('HasCompositePrimaryKey', $shortTraits, true);

    // 5. Eager loading $with
    $with = [];
    if ($ref->hasProperty('with')) {
        $p = $ref->getProperty('with');
        $with = $p->getValue($instance);
    }

    // 6. Inspect relations
    $relations = [];
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
    foreach ($methods as $m) {
        if ($m->class !== $fqcn) {
            continue;
        }
        if ($m->getNumberOfRequiredParameters() > 0) {
            continue;
        }

        $mName = $m->name;
        // Ignore known non-relation methods
        $ignored = [
            'save', 'delete', 'update', 'replicate', 'fresh', 'refresh', 'toArray', 'toJson',
            'getAttribute', 'setAttribute', 'getCompositeKey', 'casts', 'allowsUser',
            'getAncestorIds', 'getPermissionLineage', 'getIncrementing', 'getKey', 'getKeyName'
        ];
        if (in_array($mName, $ignored) || (str_starts_with($mName, 'get') && str_ends_with($mName, 'Attribute')) || str_starts_with($mName, 'scope')) {
            continue;
        }

        try {
            $val = $m->invoke($instance);
            if ($val instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $relType = (new ReflectionClass($val))->getShortName();
                $relModel = get_class($val->getRelated());
                $relModelShort = basename(str_replace('\\', '/', $relModel));

                $fk = null;
                $lk = null;
                $pivot = null;
                $pivotColumns = [];

                if ($val instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $fk = $val->getForeignPivotKeyName();
                    $lk = $val->getRelatedPivotKeyName();
                    $pivot = $val->getTable();
                    $pivotColumns = $val->getPivotColumns();
                } elseif ($val instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
                    $fk = $val->getForeignKeyName();
                    $lk = $val->getMorphType();
                } elseif ($val instanceof \Illuminate\Database\Eloquent\Relations\MorphOneOrMany) {
                    $fk = $val->getForeignKeyName();
                    $lk = $val->getQualifiedMorphType();
                } elseif ($val instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                    $fk = $val->getForeignKeyName();
                    $lk = $val->getOwnerKeyName();
                } elseif ($val instanceof \Illuminate\Database\Eloquent\Relations\HasOneOrMany) {
                    $fk = $val->getForeignKeyName();
                    $lk = $val->getLocalKeyName();
                }

                $relations[$mName] = [
                    'name' => $mName,
                    'type' => $relType,
                    'related_model' => $relModelShort,
                    'related_fqcn' => $relModel,
                    'foreign_key' => $fk,
                    'local_or_owner_key' => $lk,
                    'pivot_table' => $pivot,
                    'pivot_columns' => $pivotColumns,
                ];
            }
        } catch (\Throwable $e) {
            // Not a relation or requires params
        }
    }

    // 7. Scopes (Local and Global)
    $scopes = [];
    foreach ($methods as $m) {
        if ($m->class === $fqcn && str_starts_with($m->name, 'scope')) {
            $scopeName = lcfirst(substr($m->name, 5));
            $scopes[] = [
                'name' => $scopeName,
                'type' => 'local',
                'method' => $m->name
            ];
        }
    }
    // Global scopes from booted/boot
    if (preg_match_all('/addGlobalScope\s*\(\s*[\'"]([^\'"]+)[\'"]/', $source, $gsm)) {
        foreach ($gsm[1] as $s) {
            $scopes[] = ['name' => $s, 'type' => 'global'];
        }
    }
    if (preg_match_all('/addGlobalScope\s*\(\s*new\s+([a-zA-Z0-9_]+)/', $source, $gsm)) {
        foreach ($gsm[1] as $s) {
            $scopes[] = ['name' => $s, 'type' => 'global_class'];
        }
    }

    // 8. boot() or booted() methods, model events
    $bootMethods = [];
    $modelEvents = [];
    if (str_contains($source, 'function boot()')) {
        $bootMethods[] = 'boot';
    }
    if (str_contains($source, 'function booted()')) {
        $bootMethods[] = 'booted';
    }
    if (preg_match('/function\s+(boot[a-zA-Z0-9_]+)\s*\(/', $source, $bm)) {
        if (!in_array($bm[1], $bootMethods)) {
            $bootMethods[] = $bm[1];
        }
    }
    foreach (['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored'] as $ev) {
        if (preg_match("/static::{$ev}\s*\(/", $source)) {
            $modelEvents[] = $ev;
        }
    }
    if ($ref->hasProperty('dispatchesEvents')) {
        $de = $ref->getProperty('dispatchesEvents')->getValue($instance);
        if (!empty($de)) {
            $modelEvents[] = 'dispatchesEvents: ' . json_encode($de);
        }
    }

    // 9. lock_version / optimistic locking
    $hasLockVersion = in_array('lock_version', $fillable, true) || isset($casts['lock_version']) || str_contains($source, 'lock_version');
    $lockVersionDetails = [];
    if (in_array('lock_version', $fillable, true)) {
        $lockVersionDetails[] = 'in $fillable';
    }
    if (isset($casts['lock_version'])) {
        $lockVersionDetails[] = 'in $casts (' . $casts['lock_version'] . ')';
    }

    // 10. Potential N+1 risks
    $n1Risks = [];
    // Accessors
    foreach ($methods as $m) {
        if ($m->class === $fqcn && str_starts_with($m->name, 'get') && str_ends_with($m->name, 'Attribute')) {
            $attr = lcfirst(substr($m->name, 3, -9));
            // get method source code
            $start = $m->getStartLine();
            $end = $m->getEndLine();
            $lines = array_slice(file($file), $start - 1, $end - $start + 1);
            $body = implode('', $lines);
            
            $risks = [];
            if (str_contains($body, 'DB::')) {
                $risks[] = 'DB:: query';
            }
            foreach (array_keys($relations) as $relName) {
                if (preg_match('/\$this->' . $relName . '\b(?!\()/', $body)) {
                    $risks[] = "lazy-loads relation '{$relName}'";
                }
            }
            if (!empty($risks)) {
                $n1Risks[] = [
                    'type' => 'accessor',
                    'method' => $m->name,
                    'attribute' => $attr,
                    'risk' => implode(', ', array_unique($risks)),
                ];
            }
        }
    }

    // Custom methods
    foreach ($methods as $m) {
        if ($m->class === $fqcn && !isset($relations[$m->name]) && !str_starts_with($m->name, 'scope') && !(str_starts_with($m->name, 'get') && str_ends_with($m->name, 'Attribute'))) {
            $start = $m->getStartLine();
            $end = $m->getEndLine();
            $lines = array_slice(file($file), $start - 1, $end - $start + 1);
            $body = implode('', $lines);

            $risks = [];
            if (str_contains($body, 'DB::')) {
                $risks[] = 'DB:: queries';
            }
            if (str_contains($body, 'foreach') || str_contains($body, 'while')) {
                foreach (array_keys($relations) as $relName) {
                    if (str_contains($body, '->' . $relName)) {
                        $risks[] = "loops over relation '{$relName}'";
                    }
                }
            }
            if (preg_match('/while\s*\(\s*\$parent\s*=\s*\$parent->parent\b/', $body)) {
                $risks[] = 'recursive parent lazy traversal (N+1 query per hierarchy level)';
            }

            if (!empty($risks)) {
                $n1Risks[] = [
                    'type' => 'custom_method',
                    'method' => $m->name,
                    'risk' => implode(', ', array_unique($risks)),
                ];
            }
        }
    }

    $results[$className] = [
        'class' => $className,
        'table' => $table,
        'primary_key' => $pk,
        'composite_key' => $compositeKey,
        'incrementing' => $inc,
        'key_type' => $keyType,
        'fillable' => $fillable,
        'guarded' => $guarded,
        'casts' => $casts,
        'uses_soft_deletes' => $usesSoftDeletes,
        'uses_guards_version' => $usesGuardsVersion,
        'uses_composite_key' => $usesCompositeKeyTrait,
        'with' => $with,
        'relations' => $relations,
        'scopes' => $scopes,
        'boot_methods' => $bootMethods,
        'events' => $modelEvents,
        'has_lock_version' => $hasLockVersion,
        'lock_version_details' => $lockVersionDetails,
        'n1_risks' => $n1Risks,
    ];
}

file_put_contents(__DIR__ . '/models_complete_audit.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Audit complete! Processed " . count($results) . " models.\n";
