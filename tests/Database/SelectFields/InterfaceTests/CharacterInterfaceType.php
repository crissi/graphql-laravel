<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\InterfaceType;
use Rebing\GraphQL\Tests\Support\Models\Comment;
use Rebing\GraphQL\Tests\Support\Models\Post;

class CharacterInterfaceType extends InterfaceType
{
    protected $attributes = [
        'name' => 'CharacterInterface',
    ];

    public function types(): array
    {
        return [
            GraphQL::type('Droid'),
            GraphQL::type('Human'),
        ];
    }

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
            ]
        ];
    }

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
