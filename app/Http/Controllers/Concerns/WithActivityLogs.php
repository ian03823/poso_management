<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\LogActivity;

trait WithActivityLogs
{
    protected function currentRoleAndName(): array
    {
        if (Auth::guard('admin')->check()) {
            $u = Auth::guard('admin')->user();
            $name = $u->name ?? trim(($u->fname ?? '').' '.($u->lname ?? '')) ?: 'Admin';
            return ['Admin', $name];
        }
        if (Auth::guard('enforcer')->check()) {
            $u = Auth::guard('enforcer')->user();
            $name = trim(preg_replace('/\s+/', ' ', trim(($u->fname ?? '').' '.($u->mname ?? '').' '.($u->lname ?? '')))) ?: ($u->badge_num ?? 'Enforcer');
            $disp = $u->badge_num ? "{$name} ({$u->badge_num})" : $name;
            return ['Enforcer', $disp];
        }
        return ['System','System'];
    }

    protected function logCreated($model, string $what, array $props = []): void
    {
        [$role, $who] = $this->currentRoleAndName();
        DB::afterCommit(function () use ($model,$what,$props,$role,$who) {
            LogActivity::on($model)
                ->byCurrentUser()
                ->event("{$what}.created")
                ->withProperties($props + ['id' => $model->getKey()])
                ->fromRequest()
                ->log("{$role} {$who} created {$what} (ID: {$model->getKey()})");
        });
    }

    protected function logUpdated($model, string $what, array $props = []): void
    {
        [$role, $who] = $this->currentRoleAndName();
        $changes = $model->getChanges();
        $original = $model->getOriginal();
        $diff = [];
        foreach ($changes as $k => $v) {
            if ($k === $model->getKeyName()) continue;
            $diff[$k] = ['from' => $original[$k] ?? null, 'to' => $v];
        }

        DB::afterCommit(function () use ($model,$what,$props,$role,$who,$diff) {
            LogActivity::on($model)
                ->byCurrentUser()
                ->event("{$what}.updated")
                ->withProperties($props + ['id' => $model->getKey(), 'diff' => $diff])
                ->fromRequest()
                ->log("{$role} {$who} updated {$what} (ID: {$model->getKey()})");
        });
    }

    protected function logDeleted($model, string $what, array $props = []): void
    {
        [$role, $who] = $this->currentRoleAndName();
        DB::afterCommit(function () use ($model,$what,$props,$role,$who) {
            LogActivity::on($model)
                ->byCurrentUser()
                ->event("{$what}.deleted")
                ->withProperties($props + ['id' => $model->getKey()])
                ->fromRequest()
                ->log("{$role} {$who} deleted {$what} (ID: {$model->getKey()})");
        });
    }

    protected function logCustom($model, string $event, string $message, array $props = []): void
    {
        DB::afterCommit(function () use ($model,$event,$message,$props) {
            LogActivity::on($model)
                ->byCurrentUser()
                ->event($event)
                ->withProperties($props)
                ->fromRequest()
                ->log($message);
        });
    }
}
