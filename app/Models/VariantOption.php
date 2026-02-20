<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VariantOption extends Model
{
    protected $fillable = ['variant_group_id', 'name'];

    public function group() {
        return $this->belongsTo(VariantGroup::class, 'variant_group_id');
    }
}