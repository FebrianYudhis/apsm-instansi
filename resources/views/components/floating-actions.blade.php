@php
    $floatingActionId = $id ?? 'floating-actions-' . uniqid();
@endphp

<div class="floating-actions">
    <input type="checkbox" id="{{ $floatingActionId }}" class="floating-action-checkbox">
    <div class="floating-action-menu">
        @foreach ($actions as $action)
            @if (isset($action['form_action']))
                <form action="{{ $action['form_action'] }}" method="{{ $action['form_method'] ?? 'POST' }}" class="mb-0">
                    @if (($action['csrf'] ?? true) === true)
                        @csrf
                    @endif
                    <button type="submit"
                        @foreach (($action['attributes'] ?? []) as $attribute => $value)
                            {{ $attribute }}="{{ $value }}"
                        @endforeach
                        class="btn {{ $action['class'] ?? 'btn-primary' }} floating-action-item"
                        title="{{ $action['label'] }}"
                        aria-label="{{ $action['label'] }}">
                        <i class="{{ $action['icon'] }}"></i>
                    </button>
                </form>
            @elseif (isset($action['url']))
                <a href="{{ $action['url'] }}"
                    class="btn {{ $action['class'] ?? 'btn-primary' }} floating-action-item"
                    title="{{ $action['label'] }}"
                    aria-label="{{ $action['label'] }}">
                    <i class="{{ $action['icon'] }}"></i>
                </a>
            @else
                <button type="button"
                    @foreach (($action['attributes'] ?? []) as $attribute => $value)
                        {{ $attribute }}="{{ $value }}"
                    @endforeach
                    class="btn {{ $action['class'] ?? 'btn-primary' }} floating-action-item"
                    title="{{ $action['label'] }}"
                    aria-label="{{ $action['label'] }}">
                    <i class="{{ $action['icon'] }}"></i>
                </button>
            @endif
        @endforeach
    </div>
    <label for="{{ $floatingActionId }}" class="btn btn-dark floating-action-toggle mb-0" aria-label="Buka pilihan aksi">
        <i class="fa fa-ellipsis-h"></i>
        <i class="fa fa-times"></i>
    </label>
</div>
