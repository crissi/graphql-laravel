<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\Tests\Support\Models\Comment;
use Rebing\GraphQL\Tests\Support\Models\Like;
use Rebing\GraphQL\Tests\Support\Models\Post;
use Rebing\GraphQL\Tests\Support\Models\User;
use Rebing\GraphQL\Tests\Support\Traits\SqlAssertionTrait;
use Rebing\GraphQL\Tests\TestCaseDatabase;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod\CharactersQuery;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod\CharacterInterfaceType;
use Rebing\GraphQL\Tests\Support\Models\Character;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod\ItemType;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod\DroidType;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\NoTypesMethod\HumanType;

class NoTypesMethodInterfaceTest extends TestCaseDatabase
{
    use SqlAssertionTrait;

    public function testGeneratedInterfaceFieldsSqlQuery(): void
    {
        $droid = factory(Character::class)
            ->create([
                'type' => 'droid',
            ]);

        $human = factory(Character::class)
            ->create([
                'type' => 'human',
            ]);

        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    id
    type
    __typename
    ... on Droid {
        battery_left
        identifier
    }
    ... on Human {
        hoursOfSleepNeeded
        name
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);

        $this->assertSqlQueries(
                <<<'SQL'
select "characters"."hours_of_sleep_needed", "characters"."name", "characters"."battery_left", "characters"."id", "characters"."type" from "characters";
SQL
            );

        $expectedResult = [
            'data' => [
                'charactersQuery' => [
                    [
                        'id' => (string) $droid->id,
                        'type' => 'droid',
                        '__typename' => 'Droid',
                        'battery_left' => $droid->battery_left,
                        'identifier' => $droid->name,
                    ],
                    [
                        'id' => (string) $human->id,
                        'type' => 'human',
                        '__typename' => 'Human',
                        'hoursOfSleepNeeded' => $human->hours_of_sleep_needed,
                        'name' => $human->name
                    ],
                ],
            ],
        ];
        $this->assertSame($expectedResult, $result);
    }

    public function testGeneratedInterfaceFieldsIncludesPrimaryKeysSqlQuery(): void
    {
        $droid = factory(Character::class)
            ->create([
                'type' => 'droid',
            ]);

        $human = factory(Character::class)
            ->create([
                'type' => 'human',
            ]);

        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    type
    __typename
    ... on Droid {
        battery_left
        identifier
    }
    ... on Human {
        hoursOfSleepNeeded
        name
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);

        $this->assertSqlQueries(
                <<<'SQL'
select "characters"."hours_of_sleep_needed", "characters"."name", "characters"."battery_left", "characters"."type", "characters"."id" from "characters";
SQL
            );

        $expectedResult = [
            'data' => [
                'charactersQuery' => [
                    [
                        'type' => 'droid',
                        '__typename' => 'Droid',
                        'battery_left' => $droid->battery_left,
                        'identifier' => $droid->name,
                    ],
                    [
                        'type' => 'human',
                        '__typename' => 'Human',
                        'hoursOfSleepNeeded' => $human->hours_of_sleep_needed,
                        'name' => $human->name
                    ],
                ],
            ],
        ];
        $this->assertSame($expectedResult, $result);
    }



    public function testGeneratedInterfaceFieldWithRelationSqlQuery(): void
    {
        $droid = factory(Character::class)
            ->create([
                'type' => 'droid',
                'best_friend_id' => factory(Character::class)
                    ->create([
                        'type' => 'human',
                    ])->id
            ]);
            $human = factory(Character::class)
            ->create([
                'type' => 'human',
                'best_friend_id' => factory(Character::class)
                ->create([
                    'type' => 'droid',
                    ])->id
                ]);

        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    ... on Droid {
        identifier
    }
    ... on Human {
        name
    }
    bestFriend {
        id
        __typename
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);

        $this->assertSqlQueries(
                <<<'SQL'
select "characters"."name", "characters"."best_friend_id", "characters"."id", "characters"."type" from "characters";
select "characters"."id", "characters"."type" from "characters" where "characters"."id" in (?, ?);
SQL
            );

        $expectedResult = [
            'data' => [
                'charactersQuery' => [
                    [
                        'name' => $droid->bestFriend->name,
                        'bestFriend' => null
                    ],
                    [
                        'identifier' => $droid->name,
                        'bestFriend' => [
                            'id' => (string)$droid->bestFriend->id,
                            '__typename' => 'Human'
                        ]
                    ],
                    [
                        'identifier' => $human->bestFriend->name,
                        'bestFriend' => null
                    ],
                    [
                        'name' => $human->name,
                        'bestFriend' => [
                            'id' => (string)$human->bestFriend->id,
                            '__typename' => 'Droid'
                        ]
                    ],

                ],
            ],
        ];
        $this->assertSame($expectedResult, $result);
    }

    public function testGeneratedInterfaceFieldWithRelationAndCustomQueryOnInterfaceSqlQuery(): void
    {
        $droid = factory(Character::class)
            ->create([
                'type' => 'droid'
            ]);

        $droid->items()->create([
            'name' => 'Hammer',
            'is_heavy' => 1
        ]);

        $human = factory(Character::class)
            ->create([
                'type' => 'human'
                ]);

        $human->items()->create([
            'name' => 'Screwdriver',
            'is_heavy' => 0
        ]);

        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    ... on Droid {
        identifier
    }
    ... on Human {
        name
    }
    heavyItems {
        name
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);

        $this->assertSqlQueries(
                <<<'SQL'
select "characters"."name", "characters"."id", "characters"."type" from "characters";
select "character_items"."name", "character_items"."character_id", "character_items"."id" from "character_items" where "character_items"."character_id" in (?, ?) and "is_heavy" = ?;
SQL
            );

        $expectedResult = [
            'data' => [
                'charactersQuery' => [
                    [
                        'identifier' => $droid->name,
                        'heavyItems' => [
                            [
                                'name' => 'Hammer'
                            ]
                        ]
                    ],
                    [
                        'name' => $human->name,
                        'heavyItems' => []
                    ],

                ],
            ],
        ];
        $this->assertSame($expectedResult, $result);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('graphql.schemas.default', [
            'query' => [
                CharactersQuery::class,
            ],
        ]);

        $app['config']->set('graphql.schemas.custom', null);

        $app['config']->set('graphql.types', [
            ItemType::class,
            CharacterInterfaceType::class,
            DroidType::class,
            HumanType::class,
        ]);
    }
}
