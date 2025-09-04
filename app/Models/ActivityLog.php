<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    //
    protected $fillable = [
        'actor_type','actor_id',
        'subject_type','subject_id',
        'action','description','properties','ip','user_agent'
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function actor()  { return $this->morphTo(); }
    public function subject(){ return $this->morphTo(); }

    // Small helpers / scopes
    public function scopeAction($q, $action) {
        return $action ? $q->where('action', $action) : $q;
    }
    public function scopeActorType($q, $typeFqcn) {
        return $typeFqcn ? $q->where('actor_type', $typeFqcn) : $q;
    }
    public function scopeDateFrom($q, $from) {
        return $from ? $q->whereDate('created_at', '>=', $from) : $q;
    }
    public function scopeDateTo($q, $to) {
        return $to ? $q->whereDate('created_at', '<=', $to) : $q;
    }
    public function scopeSearch($q, $term) {
        if (!$term) return $q;
        return $q->where(function($qq) use ($term) {
            $qq->where('description', 'like', "%{$term}%")
               ->orWhere('action', 'like', "%{$term}%");
        });
    }
}
