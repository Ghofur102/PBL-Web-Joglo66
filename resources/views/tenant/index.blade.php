@extends('tenant.layouts.app')

@section('title', 'Beranda')

@section('content')
    <h2 class="text-2xl font-bold mb-4 text-gray-900">Informasi Lapangan</h2>

    <div class="flex flex-col md:flex-row justify-between items-start mb-10 gap-6">
        <div class="flex flex-col gap-4">
            <div class="text-base text-gray-800">
                Lapangan:
                <select id="fieldSelect" data-route="{{ route('tenant.booking.dashboard') }}"
                    class="border-b border-dashed border-gray-500 bg-transparent text-gray-900 font-medium outline-none cursor-pointer pb-1 focus:border-primary focus:ring-0">
                    <option value="">-- Pilih Lapangan --</option>
                    @foreach ($fields as $field)
                        <option value="{{ $field->id }}" {{ $selectedFieldId == $field->id ? 'selected' : '' }}>
                            {{ $field->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="{{ $selectedFieldId ? 'block' : 'hidden' }} text-base text-gray-800">
                Status lapangan: <span class="text-green-600 font-bold">Tersedia</span>
            </div>

            @if ($selectedFieldId)
                <x-tenant-button :href="route('tenant.booking.create-form', ['field_id' => $selectedFieldId])" class="px-6 py-2.5">
                    Pesan Sekarang
                </x-tenant-button>
            @else
                <x-tenant-button id="disabledBtn" disabled class="px-6 py-2.5">
                    Pesan Sekarang
                </x-tenant-button>
            @endif
        </div>

        <div
            class="w-full md:w-80 h-48 bg-gray-100 rounded-tenant-lg overflow-hidden flex items-center justify-center shadow-inner border border-gray-200">
            @if ($selectedField && $selectedField->image_url)
                <img src="{{ asset($selectedField->image_url) }}" alt="Gambar Lapangan" class="w-full h-full object-cover">
            @else
                <span class="text-gray-400 text-sm font-medium">Gambar tidak tersedia</span>
            @endif
        </div>
    </div>

    @if ($selectedField)
        <section class="mb-10 border-t border-gray-200 pt-6" aria-labelledby="refund-rules-heading">
            <h2 id="refund-rules-heading" class="text-xl font-bold mb-4 text-gray-900">Aturan Refund</h2>
            <div class="grid gap-6 md:grid-cols-2">
                @foreach (['cancel' => 'Pembatalan', 'reschedule' => 'Perubahan Jadwal'] as $type => $label)
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-2">{{ $label }}</h3>
                        @php($rules = $selectedField->refundRules->where('rule_type', $type)->where('is_active', true)->sortByDesc('min_hours_before'))
                        @forelse ($rules as $rule)
                            <p class="text-sm text-gray-600">
                                {{ $label }} minimal {{ $rule->min_hours_before }} jam sebelum waktu main:
                                refund {{ $rule->refund_percent }}%
                            </p>
                        @empty
                            <p class="text-sm text-gray-600">Tidak ada aturan refund aktif.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <h2 class="text-2xl font-bold mb-4 text-gray-900 mt-10">Booking Terdekat</h2>
    <div class="mb-10 flex flex-col gap-3">
        @if ($selectedField && $nearestBookings->isNotEmpty())
            @foreach ($nearestBookings as $data)
                @include('components.booking-row', [
                    'dayMonth' => $data['day_month'],
                    'year' => $data['year'],
                    'time' => $data['time'],
                    'teamName' => $data['team_name'],
                    'statusText' => $data['status_text'],
                ])
            @endforeach
        @else
            <div class="text-center p-6 border border-dashed border-gray-300 rounded-tenant-md text-gray-500 bg-gray-50">
                Tidak ada booking tersedia
            </div>
        @endif
    </div>

    <h2 class="text-2xl font-bold mb-4 text-gray-900">Riwayat</h2>
    <div class="flex flex-col gap-3">
        @if ($selectedField && $userBookings->isNotEmpty())
            @foreach ($userBookings as $data)
                @include('components.booking-row', [
                    'dayMonth' => $data['day_month'],
                    'year' => $data['year'],
                    'time' => $data['time'],
                    'teamName' => $data['team_name'],
                    'statusText' => $data['status_text'],
                ])
            @endforeach
        @else
            <div class="text-center p-6 border border-dashed border-gray-300 rounded-tenant-md text-gray-500 bg-gray-50">
                Riwayat belum tersedia
            </div>
        @endif
    </div>
@endsection
