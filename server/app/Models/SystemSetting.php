<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored runtime configuration value.
 *
 * The definition of the setting, including its type, default and validation
 * rules, lives in the authoritative catalogue (config/settings.php). This
 * model stores only the overridden value and the scope it applies to.
 */
class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'setting_key',
        'scope_unit_id',
        'scope_key',
        'value_type',
        'string_value',
        'integer_value',
        'boolean_value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_unit_id' => 'integer',
            'scope_key' => 'integer',
            'integer_value' => 'integer',
            'boolean_value' => 'boolean',
            'updated_by' => 'integer',
        ];
    }

    /**
     * The organizational unit the value applies to. Null for the global scope.
     */
    public function scopeUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'scope_unit_id');
    }

    /**
     * The administrator who last changed the value.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The typed value of the setting.
     *
     * @return string|int|bool|null
     */
    public function typedValue(): string|int|bool|null
    {
        return match ($this->value_type) {
            'integer' => $this->integer_value,
            'boolean' => (bool) $this->boolean_value,
            default => $this->string_value,
        };
    }
}
