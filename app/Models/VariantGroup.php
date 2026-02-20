<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class VariantGroup extends Model
{
    protected $fillable = ['name', 'track_stock'];

    public function options()
    {
        return $this->hasMany(VariantOption::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_variant_group');
    }
}
