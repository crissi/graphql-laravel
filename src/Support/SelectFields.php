<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Support;

use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphqlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Rebing\GraphQL\Support\AliasedRelationships\GenerateRelationshipKey;
use Rebing\GraphQL\Support\AliasedRelationships\ModelRelationshipAdder;
use Rebing\GraphQL\Support\AliasedRelationships\Resolver as AliasedRelationshipsResolver;
use RuntimeException;

class SelectFields
{
    /** @var array */
    private $select = [];
    /** @var array */
    private $relations = [];
    /**
     * @var array<class-string<Model>,array<string,string>>
     */
    private static $modelRelationshipsForAlias = [];
    const ALWAYS_RELATION_KEY = 'ALWAYS_RELATION_KEY';

    /**
     * @param  ResolveInfo  $info
     * @param  GraphqlType  $parentType
     * @param  array  $queryArgs  Arguments given with the query/mutation
     * @param  int  $depth The depth to walk the AST and introspect for nested relations
     * @param  mixed  $ctx The GraphQL context; can be anything and is only passed through
     *   Can be created/overridden by \Rebing\GraphQL\GraphQLController::queryContext
     */
    public function __construct(ResolveInfo $info, GraphqlType $parentType, array $queryArgs, int $depth, $ctx)
    {
        if ($parentType instanceof WrappingType) {
            $parentType = $parentType->getWrappedType(true);
        }

        $requestedFields = $this->getFieldSelection($info, $queryArgs, $depth);
        $fields = self::getSelectableFieldsAndRelations($queryArgs, $requestedFields, $parentType, null, true, $ctx);
        $this->select = $fields[0];
        $this->relations = $fields[1];

        $this->createModelRelationshipsForAlias();
    }

    private function getFieldSelection(ResolveInfo $resolveInfo, array $args, int $depth): array
    {
        $resolveInfoFieldsAndArguments = new ResolveInfoFieldsAndArguments($resolveInfo);

        return [
            'args' => $args,
            'fields' => $resolveInfoFieldsAndArguments->getFieldsAndArgumentsSelection($depth),
        ];
    }

    /**
     * Retrieve the fields (top level) and relations that
     * will be selected with the query.
     *
     * @param  array  $queryArgs  Arguments given with the query/mutation
     * @param  array  $requestedFields
     * @param  GraphqlType  $parentType
     * @param  Closure|null  $customQuery
     * @param  bool  $topLevel
     * @param  mixed  $ctx The GraphQL context; can be anything and is only passed through
     * @return array|Closure - if first recursion, return an array,
     *               where the first key is 'select' array and second is 'with' array.
     *               On other recursions return a closure that will be used in with
     */
    public static function getSelectableFieldsAndRelations(
        array $queryArgs,
        array $requestedFields,
        GraphqlType $parentType,
        ?Closure $customQuery = null,
        bool $topLevel = true,
        $ctx = null
    ) {
        $select = [];
        $with = [];

        if ($parentType instanceof WrappingType) {
            $parentType = $parentType->getWrappedType(true);
        }
        $parentTable = self::getTableNameFromParentType($parentType);
        $primaryKey = self::getPrimaryKeyFromParentType($parentType);

        self::handleFields($queryArgs, $requestedFields, $parentType, $select, $with, $ctx);

        // If a primary key is given, but not in the selects, add it
        if (null !== $primaryKey) {
            $primaryKey = $parentTable ? ($parentTable.'.'.$primaryKey) : $primaryKey;
            if (! in_array($primaryKey, $select)) {
                $select[] = $primaryKey;
            }
        }

        if ($topLevel) {
            return [$select, $with];
        }

        return function ($query) use ($with, $select, $customQuery, $requestedFields, $ctx) {
            if ($customQuery) {
                $query = $customQuery($requestedFields['args'], $query, $ctx);
            }

            $query->select($select);
            $query->with($with);
        };
    }

    private static function getTableNameFromParentType(GraphqlType $parentType): ?string
    {
        return isset($parentType->config['model']) ? app($parentType->config['model'])->getTable() : null;
    }

    private static function getPrimaryKeyFromParentType(GraphqlType $parentType): ?string
    {
        return isset($parentType->config['model']) ? app($parentType->config['model'])->getKeyName() : null;
    }

    /**
     * Get the selects and withs from the given fields
     * and recurse if necessary.
     *
     * @param  array  $queryArgs Arguments given with the query/mutation
     * @param  array<string,mixed>  $requestedFields
     * @param  GraphqlType  $parentType
     * @param  array  $select Passed by reference, adds further fields to select
     * @param  array  $with Passed by reference, adds further relations
     * @param  mixed  $ctx The GraphQL context; can be anything and is only passed through
     */
    protected static function handleFields(
        array $queryArgs,
        array $requestedFields,
        GraphqlType $parentType,
        array &$select,
        array &$with,
        $ctx
    ): void {
        $parentTable = self::isMongodbInstance($parentType) ? null : self::getTableNameFromParentType($parentType);

        foreach ($requestedFields['fields'] as $key => $field) {

            // Ignore __typename, as it's a special case
            if ($key === '__typename') {
                continue;
            }

            // Always select foreign key
            if ($field === self::ALWAYS_RELATION_KEY) {
                self::addFieldToSelect($key, $select, $parentTable, false);

                continue;
            }

            // If field doesn't exist on definition we don't select it
            try {
                if (method_exists($parentType, 'getField')) {
                    $fieldObject = $parentType->getField($field['name'] ?? $key);
                } else {
                    continue;
                }
            } catch (InvariantViolation $e) {
                continue;
            }

            $parentTypeUnwrapped = $parentType;

            if ($parentTypeUnwrapped instanceof WrappingType) {
                $parentTypeUnwrapped = $parentTypeUnwrapped->getWrappedType(true);
            }

            // First check if the field is even accessible
            $canSelect = self::validateField($fieldObject, $queryArgs);
            if ($canSelect === true) {
                // Add a query, if it exists
                $customQuery = $fieldObject->config['query'] ?? null;

                // Check if the field is a relation that needs to be requested from the DB
                $queryable = self::isQueryable($fieldObject->config);

                // Pagination
                if (is_a($parentType, config('graphql.pagination_type', PaginationType::class))) {
                    /* @var GraphqlType $fieldType */
                    $fieldType = $fieldObject->config['type'];
                    self::handleFields(
                        $queryArgs,
                        $field,
                        $fieldType->getWrappedType(),
                        $select,
                        $with,
                        $ctx
                    );
                }
                // With
                elseif (is_array($field['fields']) && $queryable) {
                    if (isset($parentType->config['model'])) {
                        // Get the next parent type, so that 'with' queries could be made
                        // Both keys for the relation are required (e.g 'id' <-> 'user_id')
                        $relationsKey = $fieldObject->config['alias'] ?? $field['name'];
                        $relation = call_user_func([app($parentType->config['model']), $relationsKey]);

                        self::handleRelation($select, $relation, $parentTable, $field);

                        // New parent type, which is the relation
                        $newParentType = $parentType->getField($field['name'])->config['type'];

                        self::addAlwaysFields($fieldObject, $field, $parentTable, true);

                        // Check if an graphql alias is being used
                        // and add the relationship to the model
                        // so it can be eager loaded.
                        $isGraphqlAlias = (bool) $field['alias'];

                        if ($isGraphqlAlias) {
                            $generatedRelationshipName = GenerateRelationshipKey::generate($field['alias']);
                            $fieldObject->resolveFn = new AliasedRelationshipsResolver;

                            self::collectModelRelationshipsForAlias($parentType->config['model'], GenerateRelationshipKey::generate($field['alias']), $relationsKey);
                            $relationsKey = $generatedRelationshipName;
                        }

                        $with[$relationsKey] = self::getSelectableFieldsAndRelations(
                            $queryArgs,
                            $field,
                            $newParentType,
                            $customQuery,
                            false,
                            $ctx
                        );
                    } elseif (is_a($parentTypeUnwrapped, \GraphQL\Type\Definition\InterfaceType::class)) {
                        self::handleInterfaceFields(
                            $queryArgs,
                            $field,
                            $parentTypeUnwrapped,
                            $select,
                            $with,
                            $ctx,
                            $fieldObject,
                            $key,
                            $customQuery
                        );
                    } else {
                        self::handleFields($queryArgs, $field, $fieldObject->config['type'], $select, $with, $ctx);
                    }
                }
                // Select
                else {
                    $key = isset($fieldObject->config['alias'])
                        ? $fieldObject->config['alias']
                        : $key;
                    $key = $key instanceof Closure ? $key() : $key;

                    self::addFieldToSelect($key, $select, $parentTable, false);

                    self::addAlwaysFields($fieldObject, $select, $parentTable);
                }
            }
            // If privacy does not allow the field, return it as null
            elseif ($canSelect === null) {
                $fieldObject->resolveFn = function () {
                };
            }
            // If allowed field, but not selectable
            elseif ($canSelect === false) {
                self::addAlwaysFields($fieldObject, $select, $parentTable);
            }
        }

        // If parent type is an union or interface we select all fields
        // because we don't know which other fields are required
        if (is_a($parentType, UnionType::class) || is_a($parentType, \GraphQL\Type\Definition\InterfaceType::class)) {
            $select = ['*'];
        }
    }

    /**
     * @param class-string<Model> $modelName
     * @param string $graphqlAlias
     * @param string $relationsKey
     * @return void
     */
    public static function collectModelRelationshipsForAlias(string $modelName, string $graphqlAlias, string $relationsKey): void
    {
        self::$modelRelationshipsForAlias[$modelName][] = [$graphqlAlias, $relationsKey];
    }

    public function createModelRelationshipsForAlias(): void
    {
        foreach (self::$modelRelationshipsForAlias as $model => $list) {
            ModelRelationshipAdder::add($model, $list);
        }
        self::$modelRelationshipsForAlias = [];
    }

    private static function isMongodbInstance(GraphqlType $parentType): bool
    {
        $mongoType = 'Jenssegers\Mongodb\Eloquent\Model';

        return isset($parentType->config['model']) ? app($parentType->config['model']) instanceof $mongoType : false;
    }

    /**
     * @param  string|Expression  $field
     * @param  array $select  Passed by reference, adds further fields to select
     * @param  string|null  $parentTable
     * @param  bool  $forRelation
     */
    protected static function addFieldToSelect($field, array &$select, ?string $parentTable, bool $forRelation): void
    {
        if ($field instanceof Expression) {
            $select[] = $field;

            return;
        }

        if ($forRelation && ! array_key_exists($field, $select['fields'])) {
            $select['fields'][$field] = [
                'args' => [],
                'fields' => true,
            ];
        } elseif (! $forRelation && ! in_array($field, $select)) {
            $field = $parentTable ? ($parentTable.'.'.$field) : $field;
            if (! in_array($field, $select)) {
                $select[] = $field;
            }
        }
    }

    /**
     * Check the privacy status, if it's given.
     *
     * @param  FieldDefinition  $fieldObject
     * @param  array  $queryArgs  Arguments given with the query/mutation
     * @return bool|null `true`  if selectable
     *                   `false` if not selectable, but allowed
     *                   `null`  if not allowed
     */
    protected static function validateField(FieldDefinition $fieldObject, array $queryArgs): ?bool
    {
        $selectable = true;

        // If not a selectable field
        if (isset($fieldObject->config['selectable']) && $fieldObject->config['selectable'] === false) {
            $selectable = false;
        }

        if (isset($fieldObject->config['privacy'])) {
            $privacyClass = $fieldObject->config['privacy'];

            switch ($privacyClass) {
                // If privacy given as a closure
                case is_callable($privacyClass):
                    if (false === call_user_func($privacyClass, $queryArgs)) {
                        $selectable = null;
                    }

                    break;

                // If Privacy class given
                case is_string($privacyClass):
                    $instance = app($privacyClass);
                    if (false === call_user_func([$instance, 'fire'], $queryArgs)) {
                        $selectable = null;
                    }

                    break;

                default:
                    throw new RuntimeException(
                        sprintf(
                            "Unsupported use of 'privacy' configuration on field '%s'.",
                            $fieldObject->name
                        )
                    );
            }
        }

        return $selectable;
    }

    /**
     * Determines whether the fieldObject is queryable.
     *
     * @param array $fieldObject
     *
     * @return bool
     */
    private static function isQueryable(array $fieldObject): bool
    {
        return ($fieldObject['is_relation'] ?? true) === true;
    }

    /**
     * @param  array  $select
     * @param  mixed  $relation
     * @param  string|null  $parentTable
     * @param  array  $field
     */
    private static function handleRelation(array &$select, $relation, ?string $parentTable, &$field): void
    {
        // Add the foreign key here, if it's a 'belongsTo'/'belongsToMany' relation
        if (method_exists($relation, 'getForeignKey')) {
            $foreignKey = $relation->getForeignKey();
        } elseif (method_exists($relation, 'getQualifiedForeignPivotKeyName')) {
            $foreignKey = $relation->getQualifiedForeignPivotKeyName();
        } else {
            $foreignKey = $relation->getQualifiedForeignKeyName();
        }
        $foreignKey = $parentTable ? ($parentTable.'.'.preg_replace(
            '/^'.preg_quote($parentTable, '/').'\./',
            '',
            $foreignKey
        )) : $foreignKey;
        if (is_a($relation, MorphTo::class)) {
            $foreignKeyType = $relation->getMorphType();
            $foreignKeyType = $parentTable ? ($parentTable.'.'.$foreignKeyType) : $foreignKeyType;
            if (! in_array($foreignKey, $select)) {
                $select[] = $foreignKey;
            }
            if (! in_array($foreignKeyType, $select)) {
                $select[] = $foreignKeyType;
            }
        } elseif (is_a($relation, BelongsTo::class)) {
            if (! in_array($foreignKey, $select)) {
                $select[] = $foreignKey;
            }
        } // If 'HasMany', then add it in the 'with'
        elseif ((is_a($relation, HasMany::class) || is_a($relation, MorphMany::class) || is_a(
            $relation,
            HasOne::class
        ) || is_a($relation, MorphOne::class))
            && ! array_key_exists($foreignKey, $field)) {
            $segments = explode('.', $foreignKey);
            $foreignKey = end($segments);
            if (! array_key_exists($foreignKey, $field)) {
                $field['fields'][$foreignKey] = self::ALWAYS_RELATION_KEY;
            }
            if (is_a($relation, MorphMany::class) || is_a($relation, MorphOne::class)) {
                $field['fields'][$relation->getMorphType()] = self::ALWAYS_RELATION_KEY;
            }
        }
    }

    /**
     * Add selects that are given by the 'always' attribute.
     *
     * @param  FieldDefinition  $fieldObject
     * @param  array  $select Passed by reference, adds further fields to select
     * @param  string|null  $parentTable
     * @param  bool  $forRelation
     */
    protected static function addAlwaysFields(
        FieldDefinition $fieldObject,
        array &$select,
        ?string $parentTable,
        bool $forRelation = false
    ): void {
        if (isset($fieldObject->config['always'])) {
            $always = $fieldObject->config['always'];

            if (is_string($always)) {
                $always = explode(',', $always);
            }

            // Get as 'field' => true
            foreach ($always as $field) {
                self::addFieldToSelect($field, $select, $parentTable, $forRelation);
            }
        }
    }

    /**
     * @param  array  $queryArgs
     * @param  array  $field
     * @param  GraphqlType  $parentType
     * @param  array  $select
     * @param  array  $with
     * @param  mixed  $ctx
     * @param  FieldDefinition  $fieldObject
     * @param  string  $key
     * @param  Closure|null  $customQuery
     */
    protected static function handleInterfaceFields(
        array $queryArgs,
        array $field,
        GraphqlType $parentType,
        array &$select,
        array &$with,
        $ctx,
        FieldDefinition $fieldObject,
        string $key,
        ?Closure $customQuery
    ) {
        $relationsKey = Arr::get($fieldObject->config, 'alias', $key);

        $with[$relationsKey] = function ($query) use (
            $queryArgs,
            $field,
            $parentType,
            &$select,
            $ctx,
            $customQuery,
            $key,
            $fieldObject
        ) {
            $parentTable = self::isMongodbInstance($parentType) ? null : self::getTableNameFromParentType($parentType);

            self::handleRelation($select, $query, $parentTable, $field);

            // New parent type, which is the relation
            try {
                if (method_exists($parentType, 'getField')) {
                    $newParentType = $parentType->getField($key)->config['type'];
                    $customQuery = $parentType->getField($key)->config['query'] ?? $customQuery;
                } else {
                    return $query;
                }
            } catch (InvariantViolation $e) {
                return $query;
            }

            self::addAlwaysFields($fieldObject, $field, $parentTable, true);

            // Find the type of the current relation by comparing table names
            if (isset($parentType->config['types'])) {
                $typesFiltered = array_filter(
                    $parentType->config['types'](),
                    function (GraphqlType $type) use ($query) {
                        /* @var Relation $query */
                        return app($type->config['model'])->getTable() === $query->getParent()->getTable();
                    }
                );
                $typesFiltered = array_values($typesFiltered ?? []);

                if (count($typesFiltered) === 1) {
                    /* @var GraphqlType $type */
                    $type = $typesFiltered[0];
                    $relationField = $type->getField($key);
                    $newParentType = $relationField->config['type'];
                    // If a custom query is available on the selected type, it should replace the interface's one
                    $customQuery = $relationField->config['query'] ?? $customQuery;
                }
            }

            if ($newParentType instanceof WrappingType) {
                $newParentType = $newParentType->getWrappedType(true);
            }

            /** @var callable $callable */
            $callable = self::getSelectableFieldsAndRelations(
                $queryArgs,
                $field,
                $newParentType,
                $customQuery,
                false,
                $ctx
            );

            return $callable($query);
        };
    }

    public function getSelect(): array
    {
        return $this->select;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }
}
