@props(['title' => null, 'subtitle' => null])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($title)
            <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @endif

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center justify-end gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>