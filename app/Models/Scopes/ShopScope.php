<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ShopScope implements Scope
{
    private static $isApplying = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (static::$isApplying || !auth()->hasUser()) {
            return;
        }

        static::$isApplying = true;

        try {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if (!$user) {
                return;
            }

            // Owner sees everything
            if ($user->role === 'owner') {
                return;
            }

            // Admin/Designer sees only their shop data
            if ($user->shop_id) {
                if ($model instanceof \App\Models\Shop) {
                    $builder->where('id', $user->shop_id);
                } else {
                    $builder->where('shop_id', $user->shop_id);
                }
            }
        } finally {
            static::$isApplying = false;
        }
    }
}
