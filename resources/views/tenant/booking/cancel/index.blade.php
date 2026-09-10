@extends('tenant.layouts.app')

@section('title', 'Konfirmasi Pembatalan')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Konfirmasi Pembatalan</h1>
            <p class="text-gray-600 leading-relaxed text-sm">
                Anda akan membatalkan pemesanan lapangan. Silakan periksa kembali detail
                pemesanan Anda sebelum melanjutkan pembatalan. Pastikan Anda membaca
                syarat dan ketentuan pembatalan yang berlaku.
            </p>
        </div>

        <x-tenant-card variant="flat" class="bg-primary-light border-primary/10">
            <div class="bg-gray-200 rounded-tenant-md h-36 flex items-center justify-center text-gray-400 text-sm mb-4">
                Gambar Lapangan
            </div>

            <h3 class="font-bold text-gray-900 text-lg mb-3">{{ $detail->booking->field->name ?? 'Lapangan' }}</h3>

            <div class="space-y-2 text-sm text-gray-700">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" />
                    </svg>
                    <span>{{ $playDate->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ \Carbon\Carbon::parse($detail->start_play_time)->format('H:i') }} -
                        {{ \Carbon\Carbon::parse($detail->end_play_time)->format('H:i') }} WIB</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m-4-2.803a3 3 0 100-6 3 3 0 000 6z" />
                    </svg>
                    <span>{{ $detail->booking->team_name }}</span>
                </div>
            </div>
        </x-tenant-card>
    </div>

    <x-tenant-card variant="flat" class="mb-6 p-0 overflow-hidden">
        <div class="bg-primary-light px-5 py-3 border-b border-gray-200">
            <h2 class="font-bold text-gray-900 text-sm">Syarat & Ketentuan Pembatalan</h2>
        </div>
        <div class="divide-y divide-gray-100 px-5 py-2">
            <div class="flex items-start gap-3 py-3">
                <svg class="w-5 h-5 text-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" />
                </svg>
                <p class="text-sm text-gray-700">Pembatalan maksimal dilakukan <strong>H-3</strong> sebelum jadwal bermain.
                </p>
            </div>
            <div class="flex items-start gap-3 py-3">
                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86l-8.3 14.42A1.5 1.5 0 003.7 20h16.6a1.5 1.5 0 001.31-2.22l-8.3-14.42a1.5 1.5 0 00-2.62 0z" />
                </svg>
                <p class="text-sm text-gray-700">Jika melewati batas waktu, <strong>DP hangus</strong> dan tidak dapat
                    dikembalikan.</p>
            </div>
            <div class="flex items-start gap-3 py-3">
                <svg class="w-5 h-5 text-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm text-gray-700">Jika sesuai syarat, DP dapat dikembalikan sesuai kebijakan refund.</p>
            </div>
        </div>
    </x-tenant-card>

    <x-tenant-card variant="flat" class="mb-6 p-0 overflow-hidden">
        <div class="bg-primary-light px-5 py-3 border-b border-gray-200">
            <h2 class="font-bold text-gray-900 text-sm">Alasan Pembatalan</h2>
        </div>
        <div class="px-5 py-4 flex flex-col gap-3">
            @foreach (['Tim berhalangan hadir', 'Jadwal Bentrok', 'Kondisi Cuaca', 'Alasan Lainnya'] as $option)
                <label class="flex items-center gap-3 cursor-pointer w-fit">
                    <input type="radio" name="reason_option" value="{{ $option }}"
                        class="w-4 h-4 text-primary bg-gray-100 border-gray-300 focus:ring-primary cursor-pointer">
                    <span class="text-sm text-gray-700 font-medium">{{ $option }}</span>
                </label>
            @endforeach

            {{-- 🟢 DIGANTI DARI <x-tenant-input> KE TEXTAREA MURNI AGAR ID TIDAK MENEMPEL DI DIV WRAPPER --}}
            <div class="mt-2">
                <label for="cancelReason" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Keterangan Alasan Pembatalan <span class="text-red-500">*</span>
                </label>
                <textarea id="cancelReason" rows="3" required
                    placeholder="Tuliskan alasan pembatalan Anda di sini..."
                    class="w-full px-4 py-2.5 rounded-tenant-md border border-gray-300 focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm text-gray-800 transition-all outline-none resize-none"></textarea>
            </div>
        </div>
    </x-tenant-card>

    <x-tenant-card variant="flat" class="mb-8 p-0 overflow-hidden">
        <div class="bg-primary-dark px-5 py-3">
            <h2 class="font-bold text-white text-sm">Ringkasan Status Refund / DP</h2>
        </div>
        <div class="px-5 py-4 space-y-3 border-b border-gray-100">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Nominal DP</span>
                <span class="font-semibold text-gray-900">Rp {{ number_format($netPaid, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Nominal yang Dikembalikan</span>
                <span class="font-bold text-green-600">Rp
                    {{ number_format($isRefundable ? $refundAmount : 0, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Nominal yang Hangus</span>
                <span class="font-bold text-red-600">Rp
                    {{ number_format($isRefundable ? 0 : $netPaid, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-sm border-t border-gray-200 pt-3">
                <span class="font-bold text-gray-800">Total Refund</span>
                <span class="font-black text-primary text-base">Rp
                    {{ number_format($isRefundable ? $refundAmount : 0, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-sm items-center">
                <span class="text-gray-600">Status Proses Refund</span>
                <span
                    class="text-xs bg-gray-100 px-2.5 py-1 rounded-tenant-sm font-bold text-gray-500 uppercase tracking-wide">
                    {{ $isRefundable ? 'Akan diproses setelah konfirmasi' : 'Tidak ada refund (DP Hangus)' }}
                </span>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 flex items-start gap-2 text-xs text-gray-500">
            <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
            </svg>
            <span>Refund akan diproses secara otomatis dalam 1x24 jam setelah pembatalan berhasil dikonfirmasi.</span>
        </div>
    </x-tenant-card>

    <form id="cancelForm" method="POST" action="{{ route('tenant.booking.process.cancelled', $detail->id) }}">
        @csrf
        <input type="hidden" name="detail_booking_id" value="{{ $detail->id }}">
        <input type="hidden" name="reason" id="inputReason">

        <div class="flex flex-col gap-4">
            <div class="w-full sm:w-auto">
                <x-tenant-button type="button" id="btnSubmit" disabled variant="primary"
                    class="px-8 py-3 shadow-tenant-sm">
                    Lanjut Konfirmasi &rarr;
                </x-tenant-button>
            </div>

            <label class="flex items-start gap-3 cursor-pointer w-fit">
                <input type="checkbox" id="agreeCheck"
                    class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary cursor-pointer mt-0.5">
                <span class="text-sm text-gray-600 font-medium">Dengan menekan tombol ini, anda menyetujui syarat dan
                    ketentuan diatas.</span>
            </label>
        </div>
    </form>
@endsection
