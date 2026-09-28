@php
    $caseDocuments = collect($documents ?? [])->whereNotIn('status', ['SUPERSEDED', 'INVALIDATED', 'EXPIRED'])->values();
@endphp

@if($caseDocuments->isNotEmpty())
    <details class="accountability-case-record">
        <summary class="accountability-case-record__toggle">
            <span>
                <x-icon name="report-document" size="14" />
                Related Documents
                <span class="accountability-case-record__count">{{ $caseDocuments->count() }}</span>
            </span>
            <x-icon name="chevron-down" size="14" class="accountability-case-record__chevron" />
        </summary>
        <div class="accountability-case-record__body">
            @foreach($caseDocuments as $document)
                <div class="accountability-case-record__row">
                    <span class="accountability-case-record__icon" aria-hidden="true"><x-icon name="report-document" size="15" /></span>
                    <div class="accountability-case-record__copy">
                        <strong>{{ str($document->document_type)->replace('_', ' ')->title() }}</strong>
                        <span>{{ $document->document_no }}</span>
                        <small>{{ optional($document->generated_at)->format('d M Y, g:i A') }} · {{ str($document->status)->title() }}</small>
                    </div>
                    <a class="button secondary small ui-pressable" href="{{ route('documents.preview', $document) }}">Preview</a>
                </div>
            @endforeach
        </div>
    </details>
@endif
