<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * @property int $id
 */
class Character extends Model
{
    public function bestFriend(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'best_friend_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CharacterItem::class);
    }
}
