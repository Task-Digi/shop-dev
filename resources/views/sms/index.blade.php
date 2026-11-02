@extends('layouts.app')

@section('content')
<div class="container py-4">
    @include('layouts.nav_bar')

    <div class="row justify-content-center mt-4">
        <div class="col-lg-7 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-0">SMS Solution</h1>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('sms.send') }}">
                        @csrf

                        <div class="form-group">
                            <label for="destination">Mobile number</label>
                            <input
                                id="destination"
                                name="destination"
                                value="{{ old('destination') }}"
                                class="form-control"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="e.g. 4791234567"
                                required
                            >
                            <small class="form-text text-muted">Include the country code.</small>
                        </div>

                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea
                                id="message"
                                name="message"
                                class="form-control"
                                rows="6"
                                maxlength="1000"
                                required
                            >{{ old('message') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Send SMS</button>
                    </form>

                    @if(session('sms_result'))
                        <hr>
                        <h2 class="h6">Gateway response</h2>
                        <pre class="bg-light border rounded p-3 mb-0 text-wrap">{{ json_encode(session('sms_result'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
