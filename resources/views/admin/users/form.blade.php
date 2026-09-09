@extends('layouts.app')

@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Access control</div>
        <h1>{{ $user->exists ? 'Edit user' : 'New user' }}</h1>
        <div class="muted">Manage account details, role, and access status</div>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Back to users</a>
</div>

<form class="card" method="post" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}">
    @csrf
    @if($user->exists) @method('put') @endif
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

    <div class="form-grid">
        @foreach(['name'=>'Full name','email'=>'Email','phone'=>'Phone','job_title'=>'Job title'] as $name=>$label)
            <label>{{ $label }}<input name="{{ $name }}" value="{{ old($name,$user->$name) }}" @if(in_array($name,['name','email'])) required @endif></label>
        @endforeach
        <label>Password {{ $user->exists ? '(leave blank to retain)' : '' }}<input name="password" type="password" @if(!$user->exists) required @endif></label>
        <label>Role
            <select name="role" required>
                @foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role',$user->getRoleNames()->first())===$role->name)>{{ $role->name }}</option>@endforeach
            </select>
        </label>
        <label>Status
            <select name="is_active">
                <option value="1" @selected(old('is_active',$user->is_active??true))>Active</option>
                <option value="0" @selected(!old('is_active',$user->is_active??true))>Inactive</option>
            </select>
        </label>
    </div>
    <div class="form-actions"><button class="btn">Save user</button><a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Cancel</a></div>
</form>
@endsection
