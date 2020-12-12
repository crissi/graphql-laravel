<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as GraphQLType;
use Rebing\GraphQL\Tests\Support\Models\Post;
use Rebing\GraphQL\Tests\Support\Models\Character;

class HumanType extends GraphQLType
{
    /**
     * @var array<string,string>
     */
    protected $attributes = [
        'name' => 'Human',
        'model' => Character::class,
    ];

    /**
     * @return array<string,array>
     */
    public function fields(): array
    {
        $interface = GraphQL::type('CharacterInterface');

        return
            [
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    'alias' => 'name'
                ],
                'hoursOfSleepNeeded' => [
                    'type' => Type::nonNull(Type::int()),
                    'alias' => 'hours_of_sleep_needed'
                ],
            ] + $interface->getFields();
    }

    /**
     * @return array<mixed>
     */
    public function interfaces(): array
    {
        return [
            GraphQL::type('CharacterInterface')
        ];
    }
}
