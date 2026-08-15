@props(['icon' => null, 'title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    @if ($icon)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
            {{ $icon }}
        </span>
    @endif

    <div class="min-w-0">
        @if ($title)
            <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @endif

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
</div>