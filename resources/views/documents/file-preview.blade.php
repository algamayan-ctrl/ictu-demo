@extends('layouts.app', ['title' => 'Document Preview'])
@section('content')
@php
    $fileTitle = trim((string) ($file->original_name ?? '')) ?: 'Document';
@endphp

<section class="page-heading">
    <div>
        <p class="eyebrow">Document preview</p>
        <h1>{{ $fileTitle }}</h1>
    </div>

    <a class="button secondary ui-pressable" href="{{ url()->previous() }}">
        Back
    </a>
</section>

<section class="content-area">
    @if($historicalDocument ?? null)
        <div class="callout warning" role="note">
            <strong>Historical version — not valid for current use.</strong>
            <p>
                This is superseded version {{ $historicalDocument->version_no }} of this document.
                @if($historicalDocument->superseded_at)
                    It was replaced on {{ $historicalDocument->superseded_at->format('F j, Y g:i A') }}.
                @endif
                It is kept read-only for audit and investigation. The current version is unchanged.
            </p>
        </div>
    @endif

    <x-document-review-viewer
        :file="$file"
        :title="$fileTitle"
        :preview-url="route('files.show', $file, false)"
        eyebrow=""
        :expanded="true"
        :compact-header="true"
    />
</section>
@endsection
