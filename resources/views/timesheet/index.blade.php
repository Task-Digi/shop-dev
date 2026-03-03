@extends('layouts.app')

@section('content')
<div class="card">
    @include('layouts.nav_bar')

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-5">
                        <h1 class="h3 mb-2 text-dark fw-bold">Timesheet Lønnsrapport</h1>
                        <p class="text-muted mb-4 small">Last opp Tamigo CSV-eksport for å generere STEG 1 og STEG 2 rapporter.</p>

                        @if (session('error'))
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            {{ session('error') }}
                        </div>
                        @endif

                        <form action="{{ route('timesheet.download') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="upload-area border-2 border-dashed rounded-3 p-5 text-center mb-4 bg-light position-relative" id="drop-area">
                                <i class="bi bi-file-earmark-spreadsheet fs-1 text-primary mb-3"></i>
                                <p class="mb-2 fw-medium">Velg CSV-fil fra Tamigo</p>
                                <p class="text-muted small mb-3">eller dra og slipp filen her</p>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" class="stretched-link opacity-0" required style="cursor: pointer;">
                                <div id="file-name" class="mt-2 fw-bold text-success small"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                                <i class="bi bi-download me-2"></i> Generer Lønnsrapport
                            </button>
                        </form>

                        <div class="mt-5 pt-4 border-top">
                            <h6 class="fw-bold text-dark small mb-3">Informasjon om output:</h6>
                            <div class="d-flex align-items-start mb-2">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-1 me-2" style="width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center;">1</div>
                                <p class="small text-muted mb-0"><strong>STEG 1</strong> — daglige detaljer per ansatt</p>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-1 me-2" style="width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center;">2</div>
                                <p class="small text-muted mb-0"><strong>STEG 2</strong> — sammendrag per ansatt</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .upload-area {
        transition: all 0.2s ease-in-out;
        border-color: #dee2e6 !important;
    }

    .upload-area:hover,
    .upload-area.dragover {
        border-color: #0d6efd !important;
        background-color: #f8f9fa !important;
    }

    .border-dashed {
        border-style: dashed !important;
    }
</style>

<script>
    const fileInput = document.getElementById('csv_file');
    const fileNameDisplay = document.getElementById('file-name');
    const dropArea = document.getElementById('drop-area');

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileNameDisplay.textContent = 'Valgt fil: ' + this.files[0].name;
        }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.remove('dragover');
        }, false);
    });

    dropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        if (files.length > 0) {
            fileNameDisplay.textContent = 'Valgt fil: ' + files[0].name;
        }
    }, false);
</script>
@endsection