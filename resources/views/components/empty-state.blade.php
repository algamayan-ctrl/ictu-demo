@props(['icon' => 'box', 'title', 'message' => null, 'ctaLabel' => null, 'ctaUrl' => null])

<div {{ $attributes->class(['empty-state-panel']) }}>
    <span class="empty-state-panel-icon"><x-icon :name="$icon" size="22" /></span>
    <p class="empty-state-panel-title">{{ $title }}</p>
    @if($message)
        <p class="empty-state-panel-message">{{ $message }}</p>
    @endif
    @if($ctaLabel && $ctaUrl)
        <a class="empty-state-panel-cta" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
    @endif
</div>
