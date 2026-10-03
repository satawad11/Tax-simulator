@props(['icon' => 'info', 'title', 'message' => null, 'actionLabel' => null, 'actionHref' => null])
{{--
    Milestone 09.1 — the designed empty state.

    Before M9.1 an empty list rendered as a bare white box with one grey sentence, which reads as
    a broken page rather than an expected state. The mockup's pattern is an icon, a heading that
    says what is missing, a line of context, and — where there is something useful to do — the
    action that fills it.
--}}
<div {{ $attributes->merge(['class' => 'ui-empty']) }}>
    <span class="ui-empty-icon" aria-hidden="true"><x-icon :name="$icon" class="size-7" /></span>
    <h3 class="ui-form-title">{{ $title }}</h3>
    @if ($message)
        <p class="ui-body max-w-md">{{ $message }}</p>
    @endif
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="ui-button-primary mt-1">{{ $actionLabel }}</a>
    @endif
    {{ $slot }}
</div>
