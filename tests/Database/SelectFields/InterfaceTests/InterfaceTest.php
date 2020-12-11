<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\Tests\Support\Models\Comment;
use Rebing\GraphQL\Tests\Support\Models\Like;
use Rebing\GraphQL\Tests\Support\Models\Post;
use Rebing\GraphQL\Tests\Support\Models\User;
use Rebing\GraphQL\Tests\Support\Traits\SqlAssertionTrait;
use Rebing\GraphQL\Tests\TestCaseDatabase;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\CharactersQuery;
use Rebing\GraphQL\Tests\Database\SelectFields\InterfaceTests\CharacterInterfaceType;
use Rebing\GraphQL\Tests\Support\Models\Character;

class InterfaceTest extends TestCaseDatabase
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
        //dd($droid->bestFriend);

        //$this->assertSqlQueries('');
        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    bestFriend {
        id
        __typename
    }
    ... on Droid {
        identifier
    }
    ... on Human {
        name
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);
        //dd($result);
        $this->assertSqlQueries(
                <<<'SQL'
select "characters"."name", "characters"."id", "characters"."type" from "characters";
select "characters"."id", "characters"."best_friend_id", "characters"."type" from "characters" where "characters"."best_friend_id" in (?, ?, ?, ?);
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
                        'bestFriend' => [
                            'id' => $droid->bestFriend->id,
                        ]
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

    public function testGeneratedInterfaceFieldWithRelationAndCustomQueryOnInterfaceSqlQuery(): void
    {
        $post = factory(Post::class)
            ->create([
                'title' => 'Title of the post',
            ]);
        $comment = factory(Comment::class)
            ->create([
                'title' => 'Title of the comment',
                'post_id' => $post->id,
            ]);

        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $like1 = Like::create([
            'likable_id' => $comment->id,
            'likable_type' => Comment::class,
            'user_id' => $user->id,
        ]);
        $like2 = Like::create([
            'likable_id' => $comment->id,
            'likable_type' => Comment::class,
            'user_id' => $user2->id,
        ]);

        $graphql = <<<'GRAPHQL'
{
  charactersQuery {
    id
    likes{
      likable{
        id
        title
        likes{
          id
        }
      }
    }
  }
}
GRAPHQL;

        $this->sqlCounterReset();

        $result = $this->graphql($graphql);

//         if (Application::VERSION < '5.6') {
//             $this->assertSqlQueries(
//                 <<<'SQL'
// select "users"."id" from "users";
// select "likes"."likable_id", "likes"."likable_type", "likes"."user_id", "likes"."id" from "likes" where "likes"."user_id" in (?, ?);
// select * from "comments" where "comments"."id" in (?);
// select "likes"."id", "likes"."likable_id", "likes"."likable_type" from "likes" where "likes"."likable_id" in (?) and "likes"."likable_type" = ? and 1=1;
// SQL
//             );
//         } else {
//             $this->assertSqlQueries(
//                 <<<'SQL'
// select "users"."id" from "users";
// select "likes"."likable_id", "likes"."likable_type", "likes"."user_id", "likes"."id" from "likes" where "likes"."user_id" in (?, ?);
// select * from "comments" where "comments"."id" in (?);
// select "likes"."id", "likes"."likable_id", "likes"."likable_type" from "likes" where "likes"."likable_id" in (?) and "likes"."likable_type" = ? and 1=1;
// SQL
//             );
//         }

        $expectedResult = [
            'data' => [
                'charactersQuery' => [
                    [
                        'id' => (string) $user->id,
                        'likes' => [
                            [
                                'likable' => [
                                    'id' => (string) $comment->id,
                                    'title' => $comment->title,
                                    'likes' => [
                                        [
                                            'id' => (string) $like1->id,
                                        ],
                                        [
                                            'id' => (string) $like2->id,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => (string) $user2->id,
                        'likes' => [
                            [
                                'likable' => [
                                    'id' => (string) $comment->id,
                                    'title' => $comment->title,
                                    'likes' => [
                                        [
                                            'id' => (string) $like1->id,
                                        ],
                                        [
                                            'id' => (string) $like2->id,
                                        ],
                                    ],
                                ],
                            ],
                        ],
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
                ExampleInterfaceQuery::class,
                CharactersQuery::class,
            ],
        ]);

        $app['config']->set('graphql.schemas.custom', null);

        $app['config']->set('graphql.types', [
            CharacterInterfaceType::class,
            ExampleInterfaceType::class,
            InterfaceImpl1Type::class,
            ExampleRelationType::class,
            DroidType::class,
            HumanType::class,
        ]);
    }
}
