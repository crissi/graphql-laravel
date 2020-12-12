<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod;

use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as GraphQLType;
use Rebing\GraphQL\Tests\Support\Models\Comment;
use Rebing\GraphQL\Tests\Support\Models\CharacterItem;
use GraphQL\Type\Definition\Type;

class ItemType extends GraphQLType
{
    /**
     * @var array<string,string>
     */
    protected $attributes = [
        'name' => 'Item',
        'model' => CharacterItem::class,
    ];

    public function fields(): array
    {
        return [
            'name' => [
                'type' => Type::string()
            ]
        ];
    }

 
}
