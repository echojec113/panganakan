@props(['errors' => null, 'title' => null])

@if ($errors && $errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-error']) }}>
        <div class="flex-1">
            @if ($title)
                <p class="font-semibold text-sm">{{ $title }}</p>
            @endif
            <ul class="list-disc pl-5 space-y-1 @if ($title) mt-1 @endif">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif