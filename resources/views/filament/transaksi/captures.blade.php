<div class="max-h-[calc(100dvh-10rem)] space-y-6 overflow-y-auto pe-2">
    @if ($result['message'])
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $result['message'] }}</p>
    @endif

    @foreach ($result['events'] as $event)
        <section class="space-y-3">
            <header class="flex items-center justify-between gap-3 border-b border-gray-200 pb-2 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $event['direction'] }}</h3>
                <time class="text-xs text-gray-500 dark:text-gray-400">{{ $event['time'] }}</time>
            </header>

            @if ($event['message'])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $event['message'] }}</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($event['captures'] as $capture)
                        <figure class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <img
                                src="{{ $capture['src'] }}"
                                alt="Capture {{ $event['direction'] }} dari kamera {{ $capture['camera'] }}"
                                loading="lazy"
                                decoding="async"
                                class="aspect-video w-full object-contain"
                            >
                            <figcaption class="border-t border-gray-200 px-3 py-2 text-xs text-gray-600 dark:border-white/10 dark:text-gray-300">
                                {{ $capture['camera'] }}
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>
