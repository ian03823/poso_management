<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LogActivity
{
    protected ?Model $subject = null;
    protected $actor = null;
    protected ?string $event = null;
    protected array $properties = [];
    protected ?string $ip = null;
    protected ?string $userAgent = null;

    public static function on(Model $subject): self {
        $i = new self;
        $i->subject = $subject;
        return $i;
    }

    public function by($actor): self {
        $this->actor = $actor;
        return $this;
    }

    public function event(string $event): self {
        $this->event = $event;
        return $this;
    }

    public function withProperties(array $props): self {
        $this->properties = $props;
        return $this;
    }

    public function fromRequest(?Request $request = null): self {
        $req = $request ?? request();
        $this->ip = $req->ip();
        $this->userAgent = substr($req->userAgent() ?? '', 0, 255);
        return $this;
    }

    public function log(string $description): ActivityLog {
        return ActivityLog::create([
            'actor_type'   => $this->actor ? get_class($this->actor) : null,
            'actor_id'     => $this->actor?->getKey(),
            'subject_type' => $this->subject ? get_class($this->subject) : null,
            'subject_id'   => $this->subject?->getKey(),
            'action'       => $this->event ?? 'custom',
            'description'  => $description,
            'properties'   => $this->properties ?: null,
            'ip'           => $this->ip,
            'user_agent'   => $this->userAgent,
        ]);
    }
}
