<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssetAudit
{
    public static function record(object $subject, string $action, int $actor, array $before = [], array $after = []): void
    {
        DB::table('asset_audits')->insert(['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'action' => $action, 'actor_id' => $actor, 'before' => json_encode($before), 'after' => json_encode($after), 'created_at' => now()]);
    }
}
