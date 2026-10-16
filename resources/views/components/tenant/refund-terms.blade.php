@props([
    'title',
    'terms' => [],
    'deadline' => null,
    'currentPercent' => null,
])

<section {{ $attributes->class(['rounded-tenant-lg border border-primary/20 bg-primary-light p-4 md:p-6']) }} aria-label="{{ $title }}">
    <h2 class="mb-3 text-base font-bold text-gray-900">{{ $title }}</h2>

    @if (count($terms) > 0)
        <ul class="list-disc space-y-2 pl-5 text-sm text-gray-700">
            @foreach ($terms as $term)
                <li>{{ $term['text'] }}</li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-gray-700">Hubungi admin lapangan untuk informasi syarat dan ketentuan refund.</p>
    @endif

    @if ($deadline)
        <p class="mt-4 text-sm text-gray-700">
            Batas terakhir pengajuan: <strong>{{ $deadline->copy()->locale('id')->translatedFormat('d F Y, H:i') }}</strong>
        </p>
    @endif

    @if ($currentPercent !== null)
        <p class="mt-2 text-sm text-gray-700">
            Estimasi persentase refund jika diajukan sekarang: <strong>{{ $currentPercent }}%</strong>.
            Keputusan dan nominal akhir ditetapkan admin.
        </p>
    @endif
</section>
