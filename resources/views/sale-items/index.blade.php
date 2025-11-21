@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Sale Items</h1>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="/create" class="btn btn-primary mb-2">Add Sale Item</a>
    <a href="/Dashboard" class="btn btn-success mb-2">View Sales</a>

</div>
@endsection
