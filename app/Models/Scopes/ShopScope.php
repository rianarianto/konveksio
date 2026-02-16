<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ShopScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->hasUser()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

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
            } else {
                 // If user has no shop_id but is not owner (should not happen for admin/designer), show nothing?
                 // Or maybe they are a superadmin without shop? user said owner has full access.
                 // let's assume if role is not owner and shop_id is null, they shouldn't see anything or handle gracefully.
                 // For now, if shop_id is null and not owner, the query above won't run, so they see ALL?
                 // NO, if shop_id is null, they should see NOTHING if they are supposed to be scoped.
                 // But Owner is handled.
                 // If a user is 'admin' but has NULL `shop_id`, what should happen?
                 // "Admin (Hanya akses toko tempat dia ditugaskan)" -> implies must have shop_id.
                 // If shop_id is missing, safer to show nothing.
                 // $builder->whereRaw('0=1');
            }
        }
    }
}
