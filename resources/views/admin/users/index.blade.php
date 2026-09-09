@extends('layouts.app')
@section('content')
<div class="top"><div><div class="page-kicker">Access control</div><h1>Users</h1><div class="muted">Accounts, roles, and access status</div></div><a class="btn" href="{{ route('admin.users.create') }}">＋ Add user</a></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last updated</th><th></th></tr></thead><tbody>
@forelse($users as $u)<tr><td><strong>{{ $u->name }}</strong><div class="muted">{{ $u->job_title }}</div></td><td>{{ $u->email }}</td><td><span class="status-badge status-calculated">{{ $u->getRoleNames()->join(', ') }}</span></td><td><span class="status-badge status-{{ $u->is_active?'approved':'rejected' }}">{{ $u->is_active?'Active':'Inactive' }}</span></td><td>{{ $u->updated_at->format('M d, Y') }}</td><td><a class="table-link" href="{{ route('admin.users.edit',$u) }}">Manage</a></td></tr>@empty<tr><td colspan="6" class="empty-state">No users found.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}</div>
@endsection
