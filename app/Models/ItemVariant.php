<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ItemVariant extends Model
{
    protected $fillable = ['item_id', 'group_name', 'name', 'stock'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
