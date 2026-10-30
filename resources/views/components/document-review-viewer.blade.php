@props([
    'file' => null,
    'title' => 'Scanned Borrowing Request Letter',
    'eyebrow' => 'Signed request letter',
    'emptyText' => 'No scanned request letter is available.',
    'previewUrl' => null,
    'expanded' => false,
    'compactHeader' => false,
    // Opt-in Full screen control. Off by default so existing pages are unchanged.
    'fullscreen' => false,
])

@php
    /*
     * Relative URL is intentional.
     * It keeps the iframe on the same host/port/session
     * currently used by the authenticated SPMU user.
     *
     * previewUrl lets a caller point the embedded viewer at a route with its
     * own authorization (for example documents.view for a GeneratedDocument)
     * instead of the default files.show lookup. The embedded PDF viewer keeps
     * its native Print/Download controls, so no duplicate outer action is
     * rendered here.
     */
    $previewUrl ??= $file
        ? route('files.show', $file, false)
        : null;


    $mimeType =
        strtolower((string) ($file?->mime_type ?? ''));

    $originalName =
        strtolower((string) ($file?->original_name ?? ''));

    $isPdf =
        $mimeType === 'application/pdf'
        || str_ends_with($originalName, '.pdf');

    $isImage =
        str_starts_with($mimeType, 'image/')
        || preg_match(
            '/\.(png|jpe?g|webp)$/i',
            $originalName
        );

    /*
     * Standalone preview geometry is derived from the physical file itself,
     * never from a document type/name. This keeps current and future forms
     * dynamic: portrait stays portrait, landscape stays landscape, while an
     * unreadable/unsupported page dictionary simply falls back to neutral.
     */
    $previewGeometry = $isPdf
        ? app(\App\Services\DocumentPreviewGeometryService::class)->inspect($file)
        : ['orientation' => 'unknown', 'ratio' => null];
    $previewOrientation = $previewGeometry['orientation'] ?? 'unknown';
    $previewRatio = $previewGeometry['ratio'] ?? null;
@endphp

<article
    class="card scanned-document-card{{ $expanded ? ' scanned-document-card--expanded' : '' }}{{ $compactHeader ? ' scanned-document-card--compact-header' : '' }}"
    data-document-preview-card
    data-preview-orientation="{{ $previewOrientation }}"
    @if($previewRatio) style="--document-page-ratio: {{ $previewRatio }}" @endif
>
    @if($compactHeader)
        @if(filled($eyebrow))
            <div class="scanned-document-reference">
                <span>Document no.</span>
                <strong>{{ $eyebrow }}</strong>
            </div>
        @endif
    @else
        <div class="scanned-document-header">
            <div>
                <p class="eyebrow">
                    {{ $eyebrow }}
                </p>

                <h2>
                    {{ $title }}
                </h2>
            </div>
        </div>
    @endif

    @if($fullscreen && $file && ($isPdf || $isImage))
        <div class="scanned-document-actions">
            <button
                type="button"
                class="button secondary ui-pressable scanned-document-fullscreen-toggle"
                data-document-fullscreen-toggle
                aria-pressed="false"
            >
                <svg class="scanned-document-fullscreen-enter" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg>
                <svg class="scanned-document-fullscreen-exit" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" /></svg>
                <span data-document-fullscreen-label>Full screen</span>
            </button>
        </div>
    @endif

    @if(!$file)
        <div class="scanned-document-empty">
            {{ $emptyText }}
        </div>
    @elseif($isPdf)
        <div class="scanned-pdf-stage">
            <iframe
                class="scanned-pdf-frame"
                src="{{ $previewUrl }}#page=1&view=Fit&toolbar=1&navpanes=0&scrollbar=1"
                title="{{ $title }}"
            ></iframe>
        </div>
    @elseif($isImage)
        <div
            class="scanned-image-viewer"
            data-scanned-image-viewer
        >
            <div
                class="scanned-image-toolbar"
                role="toolbar"
                aria-label="Image zoom controls"
            >
                <button
                    type="button"
                    data-image-zoom-out
                    aria-label="Zoom out"
                >
                    −
                </button>

                <span data-image-zoom-label>
                    Fit
                </span>

                <button
                    type="button"
                    data-image-zoom-in
                    aria-label="Zoom in"
                >
                    +
                </button>

                <button
                    type="button"
                    data-image-fit
                >
                    Fit
                </button>
            </div>

            <div class="scanned-image-stage">
                <img
                    data-image
                    src="{{ $previewUrl }}"
                    alt="{{ $title }}"
                >
            </div>
        </div>
    @else
        <div class="scanned-document-empty">
            Preview is unavailable for this file type.
        </div>
    @endif
</article>

@once
<style>
    .scanned-document-card {
        min-width: 0;
        overflow: hidden;
    }

    .scanned-document-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--border, #d7dee8);
    }




    .scanned-document-reference {
        display: flex;
        align-items: baseline;
        gap: 10px;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border, #d7dee8);
        color: #64748b;
        font-size: .82rem;
    }

    .scanned-document-reference strong {
        color: var(--text, #0f2744);
        font-size: .86rem;
        overflow-wrap: anywhere;
    }

    .scanned-document-header h2 {
        margin: 3px 0 0;
        font-size: 1.15rem;
    }

    .scanned-pdf-stage {
        height: clamp(460px, 56vh, 610px);
        min-height: 460px;
        background: #525659;
    }

    .scanned-pdf-frame {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
    }

    .scanned-image-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-bottom: 1px solid #d7dee8;
        background: #f4f7fa;
    }

    .scanned-image-toolbar button {
        min-width: 38px;
        height: 36px;
        border: 1px solid #bcc9d8;
        border-radius: 8px;
        background: #fff;
        color: #173b64;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    .scanned-image-toolbar span {
        min-width: 56px;
        text-align: center;
        font-weight: 700;
    }

    .scanned-image-stage {
        height: clamp(460px, 56vh, 610px);
        min-height: 460px;
        overflow: hidden;
        padding: 22px;
        background: #e7ecf1;
        display: grid;
        place-items: center;
    }

    .scanned-image-stage img {
        display: block;
        width: auto;
        max-width: 100%;
        height: auto;
        max-height: 100%;
        margin: 0 auto;
        object-fit: contain;
        background: #fff;
    }

    .scanned-document-empty {
        display: grid;
        place-items: center;
        min-height: 360px;
        padding: 24px;
        color: #64748b;
        text-align: center;
    }

    @media (max-width: 720px) {
        .scanned-document-header {
            align-items: stretch;
            flex-direction: column;
        }

        .scanned-pdf-stage,
        .scanned-image-stage {
            min-height: 380px;
            height: 54vh;
        }
    }
</style>

<script>
(() => {
    const viewers =
        document.querySelectorAll(
            '[data-scanned-image-viewer]'
        );

    viewers.forEach((viewer) => {
        if (viewer.dataset.ready === '1') {
            return;
        }

        viewer.dataset.ready = '1';

        const image =
            viewer.querySelector('[data-image]');

        const label =
            viewer.querySelector(
                '[data-image-zoom-label]'
            );

        let zoom = 100;
        let fit = true;

        const card = viewer.closest('[data-document-preview-card]');
        const syncImageOrientation = () => {
            if (!card || !image.naturalWidth || !image.naturalHeight) {
                return;
            }

            const ratio = image.naturalWidth / image.naturalHeight;
            card.dataset.previewOrientation =
                ratio > 1.08 ? 'landscape' :
                ratio < 0.92 ? 'portrait' :
                'square';
            card.style.setProperty('--document-page-ratio', ratio.toFixed(4));
        };

        image.addEventListener('load', syncImageOrientation);
        if (image.complete) {
            syncImageOrientation();
        }

        const render = () => {
            if (fit) {
                image.style.width = 'auto';
                image.style.maxWidth = '100%';
                label.textContent = 'Fit';
                return;
            }

            image.style.width = `${zoom}%`;
            image.style.maxWidth = 'none';
            label.textContent = `${zoom}%`;
        };

        viewer
            .querySelector('[data-image-zoom-out]')
            ?.addEventListener('click', () => {
                fit = false;
                zoom = Math.max(40, zoom - 10);
                render();
            });

        viewer
            .querySelector('[data-image-zoom-in]')
            ?.addEventListener('click', () => {
                fit = false;
                zoom = Math.min(250, zoom + 10);
                render();
            });

        viewer
            .querySelector('[data-image-fit]')
            ?.addEventListener('click', () => {
                fit = true;
                zoom = 100;
                render();
            });

        render();
    });
})();
</script>
@endonce

<!-- SPMU_COMPACT_REVIEW_OVERRIDE -->
<style>
/*
 * Compact approval document review.
 * The native PDF/image stage keeps its own scrolling; the page no longer
 * needs an oversized 680â€“900px preview.
 */
.formal-document-review-card,
.scanned-document-card {
    min-height: 0 !important;
    align-self: start;
}

.formal-document-review-header,
.scanned-document-header {
    padding-block: 14px !important;
}

.formal-document-review-stage,
.formal-pdf-stage,
.scanned-pdf-stage,
.scanned-image-stage {
    height: clamp(460px, 56vh, 610px) !important;
    min-height: 460px !important;
    max-height: 610px !important;
    overflow: auto;
}

.formal-document-review-frame,
.formal-pdf-frame,
.scanned-pdf-frame {
    min-height: 0 !important;
}

@media (max-width: 900px) {
    .formal-document-review-stage,
    .formal-pdf-stage,
    .scanned-pdf-stage,
    .scanned-image-stage {
        height: 52vh !important;
        min-height: 400px !important;
        max-height: 540px !important;
    }
}

@media (max-width: 620px) {
    .formal-document-review-stage,
    .formal-pdf-stage,
    .scanned-pdf-stage,
    .scanned-image-stage {
        height: 50vh !important;
        min-height: 340px !important;
        max-height: 460px !important;
    }
}
</style>

<style>
/*
 * Universal standalone preview.
 *
 * The default is always FULL-PAGE FIT: the whole first page stays visible,
 * while the available preview height changes from the physical page geometry.
 * There are no document-name rules and no forced numerical zoom. Portrait,
 * landscape, square, and future document types all use the same component.
 */
.scanned-document-card--expanded {
    width: 100%;
    margin-inline: auto;
}

/* A portrait sheet does not need a full desktop-wide card. Keeping the card
 * moderately narrow plus a tall viewer makes the full page materially more
 * readable without cropping it. */
.scanned-document-card--expanded[data-preview-orientation="portrait"] {
    max-width: 920px;
}

.scanned-document-card--expanded[data-preview-orientation="square"] {
    max-width: 1120px;
}

.scanned-document-card--expanded[data-preview-orientation="landscape"],
.scanned-document-card--expanded[data-preview-orientation="unknown"] {
    max-width: 100%;
}

.scanned-document-card--expanded[data-preview-orientation="portrait"] .scanned-pdf-stage,
.scanned-document-card--expanded[data-preview-orientation="portrait"] .scanned-image-stage {
    height: clamp(760px, 82vh, 980px) !important;
    min-height: 760px !important;
    max-height: 980px !important;
}

.scanned-document-card--expanded[data-preview-orientation="landscape"] .scanned-pdf-stage,
.scanned-document-card--expanded[data-preview-orientation="landscape"] .scanned-image-stage {
    height: clamp(600px, 70vh, 820px) !important;
    min-height: 600px !important;
    max-height: 820px !important;
}

.scanned-document-card--expanded[data-preview-orientation="square"] .scanned-pdf-stage,
.scanned-document-card--expanded[data-preview-orientation="square"] .scanned-image-stage,
.scanned-document-card--expanded[data-preview-orientation="unknown"] .scanned-pdf-stage,
.scanned-document-card--expanded[data-preview-orientation="unknown"] .scanned-image-stage {
    height: clamp(660px, 74vh, 880px) !important;
    min-height: 660px !important;
    max-height: 880px !important;
}

/* Image previews follow the same full-page-fit rule as PDFs. */
.scanned-document-card--expanded .scanned-image-stage {
    overflow: hidden !important;
}

.scanned-document-card--expanded .scanned-image-stage img {
    width: auto !important;
    height: auto !important;
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: contain !important;
}

@media (max-width: 900px) {
    .scanned-document-card--expanded,
    .scanned-document-card--expanded[data-preview-orientation] {
        max-width: 100%;
    }

    .scanned-document-card--expanded[data-preview-orientation] .scanned-pdf-stage,
    .scanned-document-card--expanded[data-preview-orientation] .scanned-image-stage {
        height: 70vh !important;
        min-height: 560px !important;
        max-height: 760px !important;
    }
}

@media (max-width: 620px) {
    .scanned-document-reference {
        align-items: flex-start;
        flex-direction: column;
        gap: 3px;
        padding: 10px 14px;
    }

    .scanned-document-card--expanded[data-preview-orientation] .scanned-pdf-stage,
    .scanned-document-card--expanded[data-preview-orientation] .scanned-image-stage {
        height: 64vh !important;
        min-height: 420px !important;
        max-height: 620px !important;
    }
}
</style>

@if($fullscreen)
@once
<style>
/*
 * Opt-in Full screen control (fullscreen prop). Uses the browser Fullscreen
 * API on the whole viewer card, so the native PDF toolbar and the image zoom
 * controls keep working. Where the API is unavailable (for example iPhone
 * Safari) the card fills the window instead.
 */
.scanned-document-actions {
    display: flex;
    justify-content: flex-end;
    padding: 8px 12px;
    border-bottom: 1px solid var(--border, #d7dee8);
    background: var(--surface-elevated, #fff);
}

.scanned-document-fullscreen-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 12px;
    font-size: 13px;
}

.scanned-document-fullscreen-exit { display: none; }
.is-document-fullscreen .scanned-document-fullscreen-enter { display: none; }
.is-document-fullscreen .scanned-document-fullscreen-exit { display: inline; }

.scanned-document-card[data-document-preview-card].is-document-fullscreen {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: none !important;
    height: 100%;
    margin: 0;
    border-radius: 0;
    background: var(--surface, #fff);
}

.scanned-document-card[data-document-preview-card].is-pseudo-fullscreen {
    position: fixed !important;
    inset: 0;
    z-index: 2147483000;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
}

.scanned-document-card[data-document-preview-card].is-document-fullscreen .scanned-pdf-stage,
.scanned-document-card[data-document-preview-card].is-document-fullscreen .scanned-image-viewer {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.scanned-document-card[data-document-preview-card].is-document-fullscreen .scanned-pdf-stage,
.scanned-document-card[data-document-preview-card].is-document-fullscreen .scanned-image-stage {
    flex: 1 1 auto;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
}

html.document-fullscreen-open { overflow: hidden; }

@media print {
    .scanned-document-actions { display: none; }
}
</style>

<script>
(() => {
    const root = document.documentElement;
    const currentFullscreen = () => document.fullscreenElement || document.webkitFullscreenElement || null;
    const canUseNative = (card) => Boolean(
        (document.fullscreenEnabled || document.webkitFullscreenEnabled)
        && (card.requestFullscreen || card.webkitRequestFullscreen)
    );
    let overlayCard = null;

    const setState = (card, active) => {
        card.classList.toggle('is-document-fullscreen', active);

        const toggle = card.querySelector('[data-document-fullscreen-toggle]');
        if (!toggle) return;

        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
        const label = toggle.querySelector('[data-document-fullscreen-label]');
        if (label) label.textContent = active ? 'Exit full screen' : 'Full screen';
    };

    const openOverlay = (card) => {
        overlayCard = card;
        card.classList.add('is-pseudo-fullscreen');
        root.classList.add('document-fullscreen-open');
        setState(card, true);
    };

    const closeOverlay = () => {
        if (!overlayCard) return;

        const card = overlayCard;
        overlayCard = null;
        card.classList.remove('is-pseudo-fullscreen');
        root.classList.remove('document-fullscreen-open');
        setState(card, false);
        card.querySelector('[data-document-fullscreen-toggle]')?.focus();
    };

    // One page-wide listener, so every viewer on the page works.
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-document-fullscreen-toggle]');
        const card = toggle?.closest('[data-document-preview-card]');
        if (!card) return;

        if (card === overlayCard) {
            closeOverlay();
            return;
        }

        if (currentFullscreen() === card) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            return;
        }

        if (!canUseNative(card)) {
            openOverlay(card);
            return;
        }

        const result = (card.requestFullscreen || card.webkitRequestFullscreen).call(card);
        if (result && typeof result.catch === 'function') {
            result.catch(() => openOverlay(card));
        }
    });

    const syncNative = () => {
        const active = currentFullscreen();

        document.querySelectorAll('[data-document-preview-card].is-document-fullscreen:not(.is-pseudo-fullscreen)')
            .forEach((card) => { if (card !== active) setState(card, false); });

        if (active && active.matches('[data-document-preview-card]')) {
            setState(active, true);
        }
    };

    document.addEventListener('fullscreenchange', syncNative);
    document.addEventListener('webkitfullscreenchange', syncNative);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlayCard) closeOverlay();
    });
})();
</script>
@endonce
@endif
