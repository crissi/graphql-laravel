<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Tests\Support\Models\User;
use Rebing\GraphQL\Tests\Support\Models\Character;

class CharactersQuery extends Query
{
    protected $attributes = [
        'name' => 'charactersQuery',
    ];

    public function type(): Type
    {
        return Type::listOf(GraphQL::type('CharacterInterface'));
    }

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
