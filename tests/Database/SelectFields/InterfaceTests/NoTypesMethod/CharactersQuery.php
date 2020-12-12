<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Tests\Support\Models\User;
use Rebing\GraphQL\Tests\Support\Models\Character;
use Illuminate\Database\Eloquent\Collection;

class CharactersQuery extends Query
{
    /**
     * @var array<string,string>
     */
    protected $attributes = [
        'name' => 'charactersQuery',
    ];

    public function type(): Type
    {
        return Type::listOf(GraphQL::type('CharacterInterface'));
    }

    /**
     * @param mixed $root
     * @param array<string,mixed> $args
     * @param mixed $contxt
     * @param ResolveInfo $info
     * @param Closure $getSelectFields
     * @return Collection
     */
    public function resolve($root, $args, $contxt, ResolveInfo $info, Closure $getSelectFields)
    {
        $fields = $getSelectFields();

        $selects = $fields->getSelect();
        return Character
            ::select($selects)
            ->when(!in_array('characters.type', $selects), function($query) {
                $query->addSelect('characters.type');
            })
            ->with($fields->getRelations())
            ->get();
    }
}
