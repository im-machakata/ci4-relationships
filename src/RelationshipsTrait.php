<?php

namespace CI4Extensions\Database;

use ReflectionObject;
use CodeIgniter\Model;
use InvalidArgumentException;

trait RelationshipsTrait
{
    protected array $validRelations      = ['hasOne', 'hasMany'];
    protected array $activeRelations     = [];
    protected array $clausesForRelations = [];
    protected array $relations = [];
    /**
     * Defines single relationship
     *
     * @param string $fieldName
     * @param object|string $model
     * @param string $foreignKey
     * @param string $localKey
     * @return boolean
     */
    public function hasOne(string $fieldName, $model, ?string $foreignKey = null, ?string $localKey = 'id')
    {
        // make sure model is string
        if (is_object($model)) {
            $model = get_class($model);
        }

        // make sure foreignKey is valid
        if (!$foreignKey) {
            $foreignKey = $this->getRelationForeignKey(model($model));
        }

        // make sure localKey is valid
        if (!$localKey) {
            $localKey = 'id';
        }

        // add relationship
        $this->relations[$fieldName] = ['hasOne', $model, $localKey, $foreignKey];
        return $this;
    }

    /**
     * Defines multiple relationships
     *
     * @param string $fieldName
     * @param string|object $model
     * @param string $foreignKey
     * @param string $localKey
     * @return boolean
     */
    public function hasMany(string $fieldName, $model, ?string $foreignKey = null, ?string $localKey = 'id')
    {
        // make sure model is string
        if (is_object($model)) {
            $model = get_class($model);
        }

        // make sure foreignKey is valid
        if (!$foreignKey) {
            $foreignKey = $this->getRelationForeignKey(model($model));
        }

        // make sure localKey is valid
        if (!$localKey) {
            $localKey = 'id';
        }

        // add relationship
        $this->relations[$fieldName] = ['hasMany', $model, $localKey, $foreignKey];
        return $this;
    }

    /**
     * Set defined relation for usage.
     */
    public function with(string $name, ?callable $clause = null): self
    {
        if (!$this->relations[$name] || !method_exists($this, $name)) {
            throw new InvalidArgumentException(sprintf('Incorrect relation name: %s', $name));
        }

        // if user used a function and not initialize, call relationship function
        if (!$this->relations[$name] && method_exists($this, $name)) {
            call_user_func([$this, $name]);
            return $this->with($name, $clause);
        }

        $this->activeRelations[$name] = $this->relations[$name];
        $this->clausesForRelations[$name] = $clause;

        if (!isset($this->afterFind[0]) || $this->afterFind[0] !== 'applyRelations') {
            array_unshift($this->afterFind, 'applyRelations');
        }

        return $this;
    }

    /**
     * Register relations via event.
     */
    protected function applyRelations(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        foreach ($this->activeRelations as $name => $relation) {
            if (!in_array($relation[0], $this->validRelations)) {
                throw new InvalidArgumentException(sprintf('Incorrect relation type: %s', $relation[0]));
            }
            $this->applyRelation($name, $relation[0], $relation[1], $relation[2] ?? null, $relation[3] ?? null, $data);
        }

        $this->activeRelations     = [];
        $this->clausesForRelations = [];

        return $data;
    }

    /**
     * Fire the relation.
     */
    protected function applyRelation(string $name, string $relationType, string $model, ?string $foreignKey, ?string $localKey, &$data)
    {
        $modelInstance = model($model);

        $foreignKey = $foreignKey ?? $this->getRelationForeignKey($modelInstance);
        $localKey   = $localKey ?? $this->primaryKey;

        $ids = $this->getRelationIds($data, $localKey);

        $modelInstance->whereIn($foreignKey, $ids);

        if (!empty($this->clausesForRelations[$name]) && is_callable($this->clausesForRelations[$name])) {
            $this->clausesForRelations[$name]();
        }

        if ($data['singleton']) {
            if ($relationType === 'hasOne') {
                $results = $modelInstance->first();
            } else {
                $results = $modelInstance->findAll();
            }

            if ($this->tempReturnType === 'array') {
                $data['data'][$name] = $results;
            } else {
                $data['data']->{$name} = $results;
            }
        } else {
            $results = $modelInstance->findAll();

            $relatedData = [];

            if ($relationType === 'hasOne') {
                foreach ($results as $row) {
                    $relatedData[$row[$foreignKey] ?? $row->{$foreignKey}] = $row;
                }
            } else {
                foreach ($results as $row) {
                    $relatedData[$this->tempReturnType === 'array' ? $row[$foreignKey] : $row->{$foreignKey}][] = $row;
                }
            }

            foreach ($data['data'] as &$row) {
                if ($this->tempReturnType === 'array') {
                    $row[$name] = $relatedData[$row[$localKey]] ?? [];
                } else {
                    $row->{$name} = $relatedData[$row->{$localKey}] ?? [];
                }
            }

            $relatedData = null;
        }
    }

    /**
     * Get IDs required by relation.
     */
    protected function getRelationIds(array $data, ?string $localKey): array
    {
        $key = $localKey ?? $this->primaryKey;

        if ($data['singleton']) {
            return $this->tempReturnType === 'array'
                ? [$data['data'][$key]]
                : [$data['data']->{$key}];
        }

        return array_column($data['data'], $key);
    }

    /**
     * Guess foreign key.
     */
    protected function getRelationForeignKey(Model $model): string
    {
        helper('inflector');

        $refObj  = new ReflectionObject($model);
        $refProp = $refObj->getProperty('primaryKey');
        $refProp->setAccessible(true);

        return sprintf('%s_%s', singular($this->table), $refProp->getValue($model));
    }
}
