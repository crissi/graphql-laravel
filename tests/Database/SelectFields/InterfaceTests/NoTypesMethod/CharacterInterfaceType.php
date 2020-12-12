<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\InterfaceType;
use Rebing\GraphQL\Tests\Support\Models\Comment;
use Rebing\GraphQL\Tests\Support\Models\Post;

class CharacterInterfaceType extends InterfaceType
{
    /**
     * @var array<string,string>
     */
    protected $attributes = [
        'name' => 'CharacterInterface',
    ];

    /**
     * @return array<string,mixed>
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::id()),
            ],
            'type' => [
                'type' => Type::nonNull(Type::string()),
            ],
            'bestFriend' => [
                'type' => GraphQL::type('CharacterInterface'),
                'always' => 'type'
            ],
            'heavyItems' => [
                'type' => Type::listOf(GraphQL::type('Item')),
                'alias' => 'items',
                'query' => function(array $params, $query) {
                    return $query->where('is_heavy', 1);
                }
            ]
        ];
    }

    /**
     * @param mixed $root
     * @return mixed
     */
    public function resolveType($root)
    {
        if ($root->type === 'droid') {
            return GraphQL::type('Droid');
        }
        if ($root->type === 'human') {
            return GraphQL::type('Human');
        }
    }
}
