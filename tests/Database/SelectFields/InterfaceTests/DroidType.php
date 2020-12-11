<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as GraphQLType;
use Rebing\GraphQL\Tests\Support\Models\User;
use Rebing\GraphQL\Tests\Support\Models\Character;

class DroidType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Droid',
        'model' => Character::class,
    ];

    public function fields(): array
    {
        $interface = GraphQL::type('CharacterInterface');

        return [
            'battery_left' => [
                'type' => Type::int(),
            ],
            'identifier' => [
                'type' => Type::nonNull(Type::string()),
                'alias' => 'name'
            ],
        ] + $interface->getFields();
    }

    public function interfaces(): array
    {
        return [
            GraphQL::type('CharacterInterface'),
        ];
    }

}
