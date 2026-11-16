@props(['name', 'value' => 10, 'sizes' => [10, 25, 50, 100], 'entriesInside' => false])
<label>Show
    <select name="{{ $name }}" aria-label="Records per page">
        @foreach($sizes as $size)<option value="{{ $size }}" @selected((int) $value === $size)>{{ $size }}{{ $entriesInside ? ' entries' : '' }}</option>@endforeach
    </select>
    @unless($entriesInside)<span class="meta">entries</span>@endunless
</label>
