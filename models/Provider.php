<?php

namespace Igniter\Socialite\Models;

use Igniter\Flame\Database\Model;

/**
 * Provider Model
 */
class Provider extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'igniter_socialite_providers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable fields
     */
    protected $fillable = ['user_id', 'provider', 'provider_id', 'token'];

    /**
     * @var array Relations
     */
    public $relation = [
        'belongsTo' => [
            'user' => ['Admin\Models\Customers_model'],
        ],
    ];
}
