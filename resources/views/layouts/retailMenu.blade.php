<div class="retail-menu">
    <nav class="navbar navbar-expand-xm navbar-light">
        <h4 class="navbar-brand mb-0">
            {{ strtoupper($heading) }}
        </h4>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item active">
                    <a class="nav-link" href="{{route('front.retail.project', ['A8H7G'])}}">Hjem</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{route('front.retail.information')}}">Prosjekt</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{route('front.retail.routine')}}">Rutiner</a>
                </li>
            </ul>
        </div>
    </nav>
</div>
