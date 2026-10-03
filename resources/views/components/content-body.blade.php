@props(['blocks'])
{{--
    M8 — renders a structured-text body.

    Every string below goes through Blade's escaping. The view emits its own markup; user text
    is never treated as markup, which is why this project stores structured text rather than
    trying to sanitize HTML. See App\Services\Content\ContentBodyFormat.
--}}
<div class="space-y-4 leading-8 text-slate-700">
    @foreach ($blocks as $block)
        @switch($block['type'])
            @case('heading')
                @php($level = min(3, max(1, $block['level'] ?? 2)) + 1)
                <p class="pt-2 font-bold text-blue-950 {{ $level === 2 ? 'text-2xl' : ($level === 3 ? 'text-xl' : 'text-lg') }}"
                   role="heading" aria-level="{{ $level }}">{{ $block['text'] }}</p>
                @break
            @case('unordered')
                <ul class="list-disc space-y-2 pl-6">
                    @foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach
                </ul>
                @break
            @case('ordered')
                <ol class="list-decimal space-y-2 pl-6">
                    @foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach
                </ol>
                @break
            @default
                <p>{{ $block['text'] }}</p>
        @endswitch
    @endforeach
</div>
