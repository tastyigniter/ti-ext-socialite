<?php

namespace Igniter\Socialite\Models;

use Igniter\Flame\Database\Model;

/**
 * Provider Model
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $token
 * @property string|null $user_type
 * @mixin \Igniter\Flame\Database\Model
 */
class Provider extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'igniter_socialite_providers';

    /**
     * @var array fillable fields
     */
    protected $fillable = ['user_type', 'user_id', 'provider', 'provider_id', 'token'];

    /**
     * @var array Relations
     */
    public $relation = [
        'morphTo' => [
            'user' => [],
        ],
    ];

    public function applyUser($user)
    {
        $this->user_id = $user->getKey();
        $this->user_type = $user->getMorphClass();

        return $this;
    }

    public function scopeWhereUser($query, $user)
    {
        $query->where('user_id', $user->getKey());
        $query->where('user_type', $user->getMorphClass());
    }
}
