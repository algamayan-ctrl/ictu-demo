@props(['id', 'name', 'label', 'icon' => 'profile', 'type' => 'text', 'value' => '', 'maxlength' => 255, 'autocomplete' => null, 'minlength' => null, 'help' => null, 'readonly' => false])
<div class="registration-field">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="registration-field-body">
        <div class="registration-input-wrap {{ $type === 'password' ? 'has-password-toggle' : '' }}">
            <x-icon :name="$icon" size="18" />
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" class="form-control"
                    @if($type !== 'password') value="{{ $value }}" @endif
                    maxlength="{{ $maxlength }}" @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                    @if($minlength) minlength="{{ $minlength }}" @endif
                    @if($help) aria-describedby="{{ $id }}-help" @endif
                    @if($errors->has($name)) aria-invalid="true" @endif @readonly($readonly) required>
            @endif
            @if($type === 'password')
                <button type="button" class="registration-password-toggle" data-password-toggle="{{ $id }}" aria-label="Show {{ strtolower($label) }}" aria-pressed="false"><x-icon name="eye" size="18" /></button>
            @endif
        </div>
        @if($help)<small id="{{ $id }}-help">{{ $help }}</small>@endif
        @error($name)<small class="registration-error">{{ $message }}</small>@enderror
    </div>
</div>
