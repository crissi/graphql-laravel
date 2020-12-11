<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterItem extends Model
{
    /**
     * @var array<string>
     */
    protected $guarded = ['id'];
}
