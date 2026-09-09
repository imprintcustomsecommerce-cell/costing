@extends('layouts.app')
@section('content')
<div class="top"><div><div class="page-kicker">Security &amp; history</div><h1>Audit logs</h1><div class="muted">Append-only record of sensitive system activity</div></div></div>
<div class="card">
    <form style="max-width:430px"><label>Search activity<input name="q" value="{{ request('q') }}" placeholder="Action or description"></label></form>
    <div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description / change</th><th>IP</th></tr></thead><tbody>
    @forelse($logs as $log)<tr><td style="white-space:nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td><td>{{ $log->user?->name??'System' }}</td><td><span class="status-badge status-calculated">{{ str_replace('_',' ',$log->action) }}</span></td><td>{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td><td>{{ $log->description }} @if($log->new_values)<code>{{ json_encode($log->new_values) }}</code>@endif</td><td>{{ $log->ip_address }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No audit records found.</td></tr>@endforelse
    </tbody></table></div>{{ $logs->links() }}
</div>
@endsection
