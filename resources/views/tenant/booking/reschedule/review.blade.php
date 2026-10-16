@extends('tenant.layouts.app')

@section('title', 'Konfirmasi Perubahan')

@section('content')
<div class="py-8 flex items-center justify-center">
    <x-tenant-card variant="default" class="w-full max-w-2xl">
        <h1 class="text-center text-2xl font-bold text-gray-900 mb-8">Konfirmasi Perubahan</h1>

        <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-4 text-center mb-8">
            <div class="text-gray-500">
                <p class="font-semibold text-sm md:text-base">{{ \Carbon\Carbon::parse($detail->play_date)->format('d F Y') }}</p>
                <p class="text-xs md:text-sm mt-1">{{ \Carbon\Carbon::parse($detail->start_play_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($detail->end_play_time)->format('H:i') }}</p>
            </div>

            <div class="text-primary text-3xl font-bold select-none">&raquo;&raquo;</div>

            <div class="text-primary-dark font-bold">
                <p class="text-base md:text-lg">{{ \Carbon\Carbon::parse($validated['new_play_date'])->format('d F Y') }}</p>
                <p class="text-xs md:text-sm text-gray-700 mt-1">{{ substr($validated['new_start_play_time'], 0, 5) }} - {{ substr($validated['new_end_play_time'], 0, 5) }}</p>
            </div>
        </div>

        <x-tenant.refund-terms
            class="mb-8"
            title="Syarat & Ketentuan Reschedule"
            :terms="$terms"
            :deadline="$deadline"
            :current-percent="$currentPercent"
        />

        <hr class="border-gray-200 mb-6">

        <div class="space-y-4 mb-8 text-sm">
            <div class="flex justify-between items-center">
                <span class="font-medium text-gray-600">Sisa tagihan awal</span>
                <span class="font-semibold text-gray-900">Rp {{ number_format($oldPrice, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="font-medium text-gray-600">Tagihan sekarang</span>
                <span class="font-bold {{ $priceDiff > 0 ? 'text-red-600' : ($priceDiff < 0 ? 'text-green-600' : 'text-gray-900') }}">
                    @if ($priceDiff > 0)
                        + Rp {{ number_format($priceDiff, 0, ',', '.') }}
                    @elseif ($priceDiff < 0)
                        - Rp {{ number_format(abs($priceDiff), 0, ',', '.') }}
                    @else
                        Rp 0
                    @endif
                </span>
            </div>
            <div class="flex justify-between items-center border-t border-gray-200 pt-4">
                <span class="font-bold text-gray-800">Total sisa tagihan</span>
                <span class="font-black text-xl text-primary">Rp {{ number_format($newPrice, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-tenant-button href="javascript:history.back()" variant="danger" class="w-full py-3">
                Batal Konfirmasi
            </x-tenant-button>
            <form method="POST" action="{{ route('tenant.booking.process.reschedule', $detail->id) }}" class="block">
                @csrf
                <input type="hidden" name="detail_booking_id" value="{{ $detail->id }}">
                <input type="hidden" name="new_play_date" value="{{ $validated['new_play_date'] }}">
                <input type="hidden" name="new_start_play_time" value="{{ $validated['new_start_play_time'] }}">
                <input type="hidden" name="new_end_play_time" value="{{ $validated['new_end_play_time'] }}">
                <input type="hidden" name="reason" value="{{ $validated['reason'] }}">
                <input type="hidden" name="confirmed" value="1">
                <x-tenant-button type="submit" variant="primary" class="w-full py-3 shadow-tenant-md">
                    Konfirmasi & Simpan
                </x-tenant-button>
            </form>
        </div>
    </x-tenant-card>
</div>
@endsection
