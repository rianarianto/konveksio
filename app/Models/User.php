<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'shop_id',
        'max_cash_advance',
        'current_cash_advance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'max_cash_advance'     => 'integer',
            'current_cash_advance' => 'integer',
        ];
    }

    /**
     * Get all tenants (shops) that this user can access.
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->role === 'owner') {
            return Shop::all();
        }

        // Admin or Designer: only their assigned shop
        return Shop::where('id', $this->shop_id)->get();
    }

    /**
     * Check if user can access a specific tenant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Owner can access all tenants
        if ($this->role === 'owner') {
            return true;
        }

        // Admin/Designer can only access their assigned shop
        return $this->shop_id === $tenant->id;
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Tugas produksi yang ditugaskan kepada user ini (assigned_to).
     */
    public function assignedProductionTasks()
    {
        return $this->hasMany(ProductionTask::class, 'assigned_to');
    }

    /**
     * Tugas produksi yang dibuat/diassign oleh user ini (assigned_by).
     */
    public function createdProductionTasks()
    {
        return $this->hasMany(ProductionTask::class, 'assigned_by');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\ShopScope);
    }

    /**
     * Riwayat kasbon (pinjaman & pelunasan) user ini.
     */
    public function cashAdvances(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(CashAdvance::class, 'cash_advanceable');
    }
}
