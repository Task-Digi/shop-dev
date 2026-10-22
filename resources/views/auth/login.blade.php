@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body p-4 p-md-5">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light p-3 mb-3" style="width: 72px; height: 72px;">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#007bff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>
                        <h1 class="h3 font-weight-bold mb-2">SHOP Login</h1>
                        <p class="text-muted small mb-0">Sign in securely using Shop Secure Identity Verification (IVS)</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger text-left small">{{ $errors->first() }}</div>
                    @endif

                    @if(session('status'))
                        <div class="alert alert-info text-left small">{{ session('status') }}</div>
                    @endif

                    <a href="{{ route('login.secureivs') }}" class="btn btn-primary btn-block btn-lg py-3 d-flex align-items-center justify-content-center shadow-sm" style="font-weight: 600; gap: 10px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Login with Shop Secure IVS</span>
                    </a>

                    <div class="mt-4 pt-3 border-top text-muted small">
                        <span>Protected by Secure Identity Verification</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
