<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $r)
    {
        return view('admin.audit.index', ['logs' => AuditLog::with('user')->when($r->q, fn ($q, $v) => $q->where('action', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%"))->latest()->paginate(50)]);
    }
}
