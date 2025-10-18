<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessStage extends Model
{
    protected $fillable = ['name', 'slug', 'order', 'active'];

    public function types()
    {
        return $this->belongsToMany(ProcessType::class, 'process_stage_type')->withTimestamps();
    }
}
