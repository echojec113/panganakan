@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full h-10 px-3 text-sm rounded-lg border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/30']) }}>