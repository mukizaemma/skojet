<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class DeskAudit
{
    public static function canRemove(?int $ownerId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $ownerId !== null && (int) $ownerId === (int) $user->id;
    }

    public static function denied(): RedirectResponse
    {
        return back()->with('error', 'Only the person who added this, or a super admin, can remove it.');
    }

    /**
     * @return array<string, int|null>
     */
    public static function owner(string $table): array
    {
        if (! Schema::hasColumn($table, 'created_by')) {
            return [];
        }

        return ['created_by' => auth()->id()];
    }

    public static function ready(): bool
    {
        return Schema::hasTable('audit_logs');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $action, string $summary, array $payload, ?string $subjectType = null, ?int $subjectId = null): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'payload' => $payload,
            'ip' => request()->ip(),
        ]);
    }
}
