@props(['title' => null, 'subtitle' => null])

<header {{ $attributes->merge(['class' => 'app-page-header flex min-w-0 max-w-full flex-wrap items-center justify-between gap-4']) }}>
    <div class="min-w-0 max-w-full [overflow-wrap:anywhere]">
        @if ($title)
            <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @endif

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="app-page-header-actions flex w-full min-w-0 max-w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
            {{ $actions }}
        </div>
    @endisset
</header>
