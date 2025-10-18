<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessType extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    public function stages()
    {
        return $this->belongsToMany(ProcessStage::class, 'process_stage_type')->withTimestamps();
    }
}
