@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => '',
    'required' => false,
    'rows' => 3
])

@php
    $hasError = $errors->has($name);
    $isTextarea = $type === 'textarea';
    $isPassword = $type === 'password';

    $baseClasses = 'w-full px-4 py-3 bg-gray-50 border rounded-tenant-md text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-1 transition-all outline-none text-sm';

    if ($isTextarea) {
        $baseClasses .= ' resize-none';
    } elseif ($isPassword || $slot->isNotEmpty()) {
        $baseClasses .= ' pr-6';
    }

    $statusClasses = $hasError
        ? 'border-red-300 focus:border-red-500 focus:ring-red-500 text-red-900'
        : 'border-gray-200 focus:border-primary focus:ring-primary';
@endphp

<div class="w-full">
    @if($label)
        <label for="{{ $name }}" class="block text-gray-700 text-sm font-medium mb-2">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if($isTextarea)
            <textarea
                id="{{ $name }}"
                name="{{ $name }}"
                rows="{{ $rows }}"
                placeholder="{{ $placeholder }}"
                {{ $required ? 'required' : '' }}
                {{ $attributes->merge(['class' => $baseClasses . ' ' . $statusClasses]) }}
            >{{ old($name, $value) }}</textarea>
        @else
            <input
                type="{{ $type }}"
                id="{{ $name }}"
                name="{{ $name }}"
                value="{{ old($name, $value) }}"
                placeholder="{{ $placeholder }}"
                {{ $required ? 'required' : '' }}
                {{ $attributes->merge(['class' => $baseClasses . ' ' . $statusClasses]) }}
            >
        @endif

        @if(!$isTextarea && $slot->isNotEmpty())
            <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                {{ $slot }}
            </div>
        @endif
    </div>

    @error($name)
        <p class="mt-2 text-xs font-semibold text-red-600">
            ⚠️ {{ $message }}
        </p>
    @enderror
</div>
