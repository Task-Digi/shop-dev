<div class="row">
    <div class="col-12 col-sm-12 col-md-12 col-lg-12 ml-auto mr-auto mb-3">
        <div class="nav-menu">
            <nav class="navbar navbar-expand-lg navbar-light p-0">
                <div class="container-fluid pl-0 pr-0">
                    {{--                                    <a class="navbar-brand" href="#">Navbar</a>--}}
                    <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            @if(request()->user != 'høyskolen-kristiania')
                            <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard') active @endif">
                                <a class="nav-link" aria-current="page" href="{{route('front.dashboard', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 1</a>
                            </li>
                            <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.two') active @endif">
                                <a class="nav-link" aria-current="page" href="{{route('front.dashboard.two', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 2</a>
                            </li>
                            <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.three') active @endif">
                                <a class="nav-link" aria-current="page" href="{{route('front.dashboard.three', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 3</a>
                            </li>
                            <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.four') active @endif">
                                <a class="nav-link" aria-current="page" href="{{route('front.dashboard.four', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 4</a>
                            </li>
                            @else
                                <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.multiple.one') active @endif">
                                    <a class="nav-link" aria-current="page" href="{{route('front.dashboard.multiple.one', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 1</a>
                                </li>
                                <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.multiple.two') active @endif">
                                    <a class="nav-link" aria-current="page" href="{{route('front.dashboard.multiple.two', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 2</a>
                                </li>
                                <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.multiple.three') active @endif">
                                    <a class="nav-link" aria-current="page" href="{{route('front.dashboard.multiple.three', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 3</a>
                                </li>
                                <li class="nav-item @if(Route::currentRouteName() == 'front.dashboard.multiple.four') active @endif">
                                    <a class="nav-link" aria-current="page" href="{{route('front.dashboard.multiple.four', ['user' => $userEncode, 'standard' => $standard['seo']])}}">Dashboard 4</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
