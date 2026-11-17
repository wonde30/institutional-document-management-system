<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes the runtime configuration catalogue.
 *
 * The catalogue in config/settings.php is authoritative: a key that is not
 * declared there cannot be stored, edited or reset, and the type, the default
 * and the validation rules always come from the catalogue rather than from
 * the client.
 */
class SettingsService
{
    /**
     * Cache prefix for the resolved settings.
     */
    protected const CACHE_PREFIX = 'settings:resolved';

    /**
     * Cache lifetime in seconds.
     */
    protected const CACHE_TTL = 300;

    public function __construct(
        protected CacheRepository $cache,
    ) {
    }

    /**
     * The catalogue of runtime settings.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return (array) config('settings.settings', []);
    }

    /**
     * The catalogue of configuration categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function categories(): array
    {
        return (array) config('settings.categories', []);
    }

    /**
     * The definition of a single setting, or null when it is not declared.
     *
     * @return array<string, mixed>|null
     */
    /**
     * Supported compatibility aliases mapping deprecated or alternative keys
     * to authoritative configuration keys.
     *
     * @var array<string, string>
     */
    protected const KEY_ALIASES = [
        'institution.logo_path' => 'institution.approved_logo_path',
    ];

    /**
     * Resolve any alias to its authoritative configuration key.
     */
    public function canonicalKey(string $key): string
    {
        return self::KEY_ALIASES[$key] ?? $key;
    }

    public function definition(string $key): ?array
    {
        $key = $this->canonicalKey($key);
        $definition = $this->catalogue()[$key] ?? null;

        return is_array($definition) ? $definition : null;
    }

    /**
     * Whether the setting may be edited by an administrator.
     */
    public function isEditable(string $key): bool
    {
        $definition = $this->definition($key);

        return $definition !== null && (bool) ($definition['editable'] ?? false);
    }

    /**
     * Whether the setting may be stored for a specific organizational unit.
     */
    public function allowsUnitScope(string $key): bool
    {
        $definition = $this->definition($key);

        return $definition !== null && ($definition['scope'] ?? 'global') === 'global_or_unit';
    }

    /**
     * Resolve the effective value of a setting.
     *
     * A value stored for the given unit takes precedence over the global
     * value, which in turn takes precedence over the catalogue default.
     */
    public function value(string $key, ?int $unitId = null): string|int|bool|null
    {
        $key = $this->canonicalKey($key);
        $definition = $this->definition($key);

        if ($definition === null) {
            return null;
        }

        $cacheKey = $this->cacheKey($key, $unitId);

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $stored = SystemSetting::query()
            ->where('setting_key', $key)
            ->when(
                $unitId !== null,
                fn ($query) => $query->where('scope_unit_id', $unitId),
                fn ($query) => $query->whereNull('scope_unit_id')
            )
            ->first();

        $value = $stored?->typedValue() ?? $definition['default'];

        $this->cache->put($cacheKey, ['value' => $value], self::CACHE_TTL);

        return $value;
    }

    /**
     * Resolve the effective value of a setting, honouring the unit scoped
     * value before the global value.
     */
    public function effectiveValue(string $key, ?int $unitId = null): string|int|bool|null
    {
        $key = $this->canonicalKey($key);
        $definition = $this->definition($key);

        if ($definition === null) {
            return null;
        }

        if ($unitId !== null && $this->allowsUnitScope($key)) {
            $unitValue = SystemSetting::query()
                ->where('setting_key', $key)
                ->where('scope_unit_id', $unitId)
                ->first();

            if ($unitValue) {
                return $unitValue->typedValue();
            }
        }

        return $this->value($key, null);
    }

    /**
     * The full catalogue with the current and default values resolved.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogueWithValues(?int $unitId = null): array
    {
        $stored = SystemSetting::query()
            ->with('scopeUnit')
            ->get()
            ->keyBy(fn (SystemSetting $setting) => $setting->setting_key.'@'.($setting->scope_unit_id ?? 0));

        $result = [];

        foreach ($this->catalogue() as $key => $definition) {
            $scopeUnitId = null;

            if ($unitId !== null && $this->allowsUnitScope($key)) {
                $unitRow = $stored->get($key.'@'.$unitId);
                if ($unitRow) {
                    $scopeUnitId = $unitId;
                }
            }

            $globalRow = $stored->get($key.'@0');

            $row = $scopeUnitId !== null ? $stored->get($key.'@'.$scopeUnitId) : $globalRow;

            $result[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'type' => $definition['type'],
                'options' => $definition['options'] ?? null,
                'default' => $definition['default'],
                'value' => $row?->typedValue() ?? $definition['default'],
                'scope' => $definition['scope'],
                'scope_unit_id' => $scopeUnitId,
                'editable' => (bool) ($definition['editable'] ?? false),
                'is_default' => $row === null,
                'updated_at' => $row?->updated_at?->toIso8601String(),
                'updated_by' => $row?->updatedBy?->name,
            ];
        }

        return $result;
    }

    /**
     * Store a value for a setting.
     *
     * @param  string|int|bool|null  $value
     * @return array{setting: array<string, mixed>, previous: string|int|bool|null}
     */
    public function set(string $key, string|int|bool|null $value, ?int $unitId, Request $request): array
    {
        $key = $this->canonicalKey($key);
        $definition = $this->definition($key);

        if ($definition === null) {
            abort(422, "The configuration key '{$key}' is not part of the configuration catalogue.");
        }

        if (! $this->isEditable($key)) {
            abort(422, "The configuration key '{$key}' is not editable.");
        }

        if ($unitId !== null && ! $this->allowsUnitScope($key)) {
            abort(422, "The configuration key '{$key}' does not support an organizational unit scope.");
        }

        try {
            $validated = Validator::make(
                ['value' => $value],
                ['value' => $definition['rules']],
                ['value' => $definition['label']],
            )->validate();
        } catch (ValidationException $exception) {
            // The failure is reported against the configuration key, so the
            // interface can attach it to the setting that was refused.
            throw ValidationException::withMessages([
                $key => $exception->errors()['value'] ?? ['The value is not valid.'],
            ]);
        }

        $normalised = $this->normalise($definition['type'], $validated['value']);

        $previous = SystemSetting::query()
            ->where('setting_key', $key)
            ->when(
                $unitId !== null,
                fn ($query) => $query->where('scope_unit_id', $unitId),
                fn ($query) => $query->whereNull('scope_unit_id')
            )
            ->first();

        $previousValue = $previous?->typedValue() ?? $definition['default'];

        DB::transaction(function () use ($key, $definition, $normalised, $unitId, $request, $previous, $previousValue) {
            $attributes = [
                'value_type' => $definition['type'],
                'string_value' => null,
                'integer_value' => null,
                'boolean_value' => null,
                'updated_by' => $request->user()?->id,
            ];

            $attributes = array_merge($attributes, $this->typedColumns($definition['type'], $normalised));

            if ($previous) {
                $previous->update($attributes);
                $setting = $previous;
            } else {
                $setting = SystemSetting::create(array_merge($attributes, [
                    'setting_key' => $key,
                    'scope_unit_id' => $unitId,
                    'scope_key' => $unitId ?? 0,
                ]));
            }

            // The audit event is written inside the same transaction, so a
            // failed update can never leave a misleading success record.
            AuditService::log(
                action: 'settings.updated',
                targetType: 'system_setting',
                targetId: $key,
                details: [
                    'setting_key' => $key,
                    'scope_unit_id' => $unitId,
                    'previous_value' => $previousValue,
                    'new_value' => $normalised,
                    'operation' => 'update',
                ],
                actorId: $request->user()?->id,
                request: $request,
            );

            $this->forget($key, $unitId);
        });

        return [
            'setting' => $this->catalogueWithValues($unitId)[0] ?? [],
            'previous' => $previousValue,
        ];
    }

    /**
     * Reset a setting to its catalogue default.
     *
     * @return array{setting: array<string, mixed>, previous: string|int|bool|null}
     */
    public function reset(string $key, ?int $unitId, Request $request): array
    {
        $key = $this->canonicalKey($key);
        $definition = $this->definition($key);

        if ($definition === null) {
            abort(422, "The configuration key '{$key}' is not part of the configuration catalogue.");
        }

        if (! $this->isEditable($key)) {
            abort(422, "The configuration key '{$key}' is not editable.");
        }

        if ($unitId !== null && ! $this->allowsUnitScope($key)) {
            abort(422, "The configuration key '{$key}' does not support an organizational unit scope.");
        }

        $previous = SystemSetting::query()
            ->where('setting_key', $key)
            ->when(
                $unitId !== null,
                fn ($query) => $query->where('scope_unit_id', $unitId),
                fn ($query) => $query->whereNull('scope_unit_id')
            )
            ->first();

        $previousValue = $previous?->typedValue() ?? $definition['default'];

        DB::transaction(function () use ($key, $unitId, $request, $previous, $previousValue) {
            $previous?->delete();

            AuditService::log(
                action: 'settings.reset',
                targetType: 'system_setting',
                targetId: $key,
                details: [
                    'setting_key' => $key,
                    'scope_unit_id' => $unitId,
                    'previous_value' => $previousValue,
                    'new_value' => null,
                    'operation' => 'reset',
                ],
                actorId: $request->user()?->id,
                request: $request,
            );

            $this->forget($key, $unitId);
        });

        return [
            'setting' => $this->catalogueWithValues($unitId)[0] ?? [],
            'previous' => $previousValue,
        ];
    }

    /**
     * Reset every setting of a category to its catalogue default.
     *
     * @return list<string>
     */
    public function resetCategory(string $category, ?int $unitId, Request $request): array
    {
        $keys = array_keys(array_filter(
            $this->catalogue(),
            fn (array $definition) => ($definition['category'] ?? null) === $category
        ));

        if (empty($keys)) {
            abort(422, "The configuration category '{$category}' is not part of the configuration catalogue.");
        }

        $reset = [];

        foreach ($keys as $key) {
            if (! $this->isEditable($key)) {
                continue;
            }

            $this->reset($key, $unitId, $request);
            $reset[] = $key;
        }

        return $reset;
    }

    /**
     * The deployment configuration boundary, without any secret value.
     *
     * @return list<array<string, mixed>>
     */
    public function deploymentBoundary(): array
    {
        return array_map(fn (array $entry) => [
            'key' => $entry['key'],
            'label' => $entry['label'],
            'sensitive' => (bool) ($entry['sensitive'] ?? false),
            // A sensitive entry never carries a value.
            'value' => ($entry['sensitive'] ?? false) ? null : ($entry['value'] ?? null),
            'managed' => 'deployment',
        ], (array) config('settings.deployment', []));
    }

    /**
     * The settings that may be published without authentication.
     *
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $result = [];

        foreach ((array) config('settings.public', []) as $key) {
            $definition = $this->definition($key);

            if ($definition === null) {
                continue;
            }

            $result[$key] = $this->value($key, null);
        }

        return $result;
    }

    /**
     * Discard the cached resolution of a setting.
     */
    public function forget(string $key, ?int $unitId = null): void
    {
        $key = $this->canonicalKey($key);
        $this->cache->forget($this->cacheKey($key, $unitId));
        $this->cache->forget($this->cacheKey($key, null));

        // The unit scoped resolution of every catalogue entry depends on the
        // global value, so the whole catalogue is discarded.
        foreach (array_keys($this->catalogue()) as $catalogueKey) {
            $this->cache->forget($this->cacheKey($catalogueKey, null));
        }
    }

    /**
     * The cache key of a resolved setting.
     */
    protected function cacheKey(string $key, ?int $unitId): string
    {
        return self::CACHE_PREFIX.':'.$key.':'.($unitId ?? 'global');
    }

    /**
     * Cast a validated value to the type declared by the catalogue.
     */
    protected function normalise(string $type, mixed $value): string|int|bool
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            default => (string) $value,
        };
    }

    /**
     * The value columns for a typed value.
     *
     * @return array<string, mixed>
     */
    protected function typedColumns(string $type, string|int|bool $value): array
    {
        return match ($type) {
            'integer' => ['integer_value' => $value],
            'boolean' => ['boolean_value' => $value],
            default => ['string_value' => $value],
        };
    }
}
