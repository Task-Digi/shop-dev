<div class="messageouter">
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session()->get('message') }}
{{--            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>--}}
        </div>
    @elseif(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session()->get('error') }}
{{--            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>--}}
        </div>
    @endif
</div>
