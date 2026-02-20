<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\VariantOption;
class VariantGroup extends Model
{
    protected $fillable = ['name', 'track_stock'];

    public function options() {
        return $this->hasMany(VariantOption::class);
    }
    public function items() {
        return $this->belongsToMany(Item::class, 'item_variant_group');
    }
}