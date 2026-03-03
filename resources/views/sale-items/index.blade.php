@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0 text-gradient font-weight-bold">Sale Items</h1>
            <p class="text-muted small mb-0">Manage your product catalog</p>
        </div>
        <div>
            <a href="/Dashboard" class="btn btn-modern-secondary mr-2">
                <i class="fa fa-arrow-left mr-1"></i> View Sales
            </a>
            <a href="/create" class="btn btn-modern-primary">
                <i class="fa fa-plus mr-1"></i> Add Sale Item
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success fade-in shadow-sm border-0 mb-4" role="alert">
            <i class="fa fa-check-circle mr-2"></i> {{ session('success') }}
        </div>
    @endif

    <div class="modern-card">
        <div class="text-center py-5">
            <div class="mb-3 text-muted" style="opacity: 0.5;">
                <i class="fa fa-shopping-basket fa-4x"></i>
            </div>
            <h3 class="h5 text-muted">Ready to manage items</h3>
            <p class="text-muted small">Select an action from the menu above to get started.</p>
        </div>
    </div>

</div>
@endsection
