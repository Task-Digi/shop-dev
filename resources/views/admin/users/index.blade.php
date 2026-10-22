@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">User Access</h1>
        <a href="{{ route('order-delivery.index') }}" class="btn btn-outline-secondary">Back to Orders</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Create user</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Name</label><input name="name" class="form-control" required></div>
                    <div class="form-group col-md-3"><label>Email</label><input name="email" type="email" class="form-control" required></div>
                    <div class="form-group col-md-2"><label>Password</label><input name="password" type="password" class="form-control" required minlength="8"></div>
                    <div class="form-group col-md-2"><label>Confirm password</label><input name="password_confirmation" type="password" class="form-control" required minlength="8"></div>
                    <div class="form-group col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input name="orders_enabled" value="1" type="checkbox" id="new-orders" class="form-check-input"><label for="new-orders" class="form-check-label">Orders access</label></div></div>
                </div>
                <button class="btn btn-primary">Create user</button>
            </form>
        </div>
    </div>

    @foreach($users as $user)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf @method('PUT')
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3"><label>Name</label><input name="name" value="{{ $user->name }}" class="form-control" required></div>
                        <div class="form-group col-md-3"><label>Email</label><input name="email" value="{{ $user->email }}" type="email" class="form-control" required></div>
                        <div class="form-group col-md-2"><label>New password</label><input name="password" type="password" class="form-control" minlength="8"></div>
                        <div class="form-group col-md-2"><label>Confirm password</label><input name="password_confirmation" type="password" class="form-control" minlength="8"></div>
                        <div class="form-group col-md-2">
                            <div class="form-check"><input name="is_active" value="1" type="checkbox" id="active-{{ $user->id }}" class="form-check-input" @checked($user->is_active)><label for="active-{{ $user->id }}" class="form-check-label">Active</label></div>
                            <div class="form-check"><input name="orders_enabled" value="1" type="checkbox" id="orders-{{ $user->id }}" class="form-check-input" @checked($user->is_admin || $user->services->contains('slug', 'orders')) @disabled($user->is_admin)><label for="orders-{{ $user->id }}" class="form-check-label">Orders access</label></div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Save</button>
                    @if($user->is_admin)<span class="badge badge-dark ml-2">Administrator</span>@endif
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
