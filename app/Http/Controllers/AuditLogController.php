<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function activities(Request $request)
    {
        $this->authorizeAudit();

        return view('content-management.audit.index', [
            'title' => 'System activities',
            'logs' => $this->query($request)->paginate(30)->withQueryString(),
            'filters' => ['q' => trim((string) $request->input('q', ''))],
            'archived' => false,
        ]);
    }

    public function archived(Request $request)
    {
        $this->authorizeAudit();

        return view('content-management.audit.index', [
            'title' => 'Archived records',
            'logs' => $this->query($request)->whereIn('action', AuditLog::ARCHIVED)->paginate(30)->withQueryString(),
            'filters' => ['q' => trim((string) $request->input('q', ''))],
            'archived' => true,
        ]);
    }

    private function authorizeAudit(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    private function query(Request $request): Builder
    {
        $query = AuditLog::query()->with('user')->latest();
        $term = trim((string) $request->input('q', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('summary', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhereHas('user', function (Builder $user) use ($like) {
                        $user->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    });
            });
        }

        return $query;
    }
}
