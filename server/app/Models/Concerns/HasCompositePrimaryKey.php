<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositePrimaryKey
{
    /**
     * Get the composite primary key columns.
     *
     * @return array<int, string>
     */
    abstract public function getCompositeKey(): array;

    /**
     * Determine if the model uses incrementing IDs.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Get the primary key for the model as an associative array.
     */
    public function getKey(): mixed
    {
        $keys = [];
        foreach ($this->getCompositeKey() as $key) {
            $keys[$key] = $this->getAttribute($key);
        }
        return $keys;
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->getCompositeKey() as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }
        return $query;
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());
        return $query->delete();
    }

    /**
     * Reload a fresh model instance from the database.
     */
    public function fresh($with = [])
    {
        if (! $this->exists) {
            return null;
        }

        $query = $this->newQueryWithoutScopes();
        foreach ($this->getCompositeKey() as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        if (! empty($with)) {
            $query->with(is_string($with) ? func_get_args() : $with);
        }

        return $query->first();
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     */
    public function refresh()
    {
        if (! $this->exists) {
            return $this;
        }

        $query = $this->newQueryWithoutScopes();
        foreach ($this->getCompositeKey() as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        $fresh = $query->first();
        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->load($this->relations);
        }

        return $this;
    }

    /**
     * Static helper to find a model by its composite key columns.
     *
     * @param array<string, mixed> $keys
     * @return static|null
     */
    public static function findComposite(array $keys): ?static
    {
        $query = static::query();
        foreach ($keys as $col => $val) {
            $query->where($col, $val);
        }
        return $query->first();
    }
}
