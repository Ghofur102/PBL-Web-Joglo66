@extends('tenant.layouts.app')

@section('title', 'Konfirmasi Penyewaan')

@section('content')
<div id="checkout-app">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900">Selesaikan Pemesanan</h2>
        <p class="text-gray-500">Lengkapi data di bawah ini untuk melanjutkan pembayaran.</p>
    </div>

    <form action="{{ route('tenant.booking.store') }}" method="POST">
        @csrf
        <input type="hidden" name="field_id" value="{{ $field->id }}">
        <input type="hidden" name="booking_data" value="{{ json_encode($groupedSlots) }}">
        <input type="hidden" name="total_price" value="{{ $totalPrice }}">

        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-tenant.refund-terms
                title="Syarat & Ketentuan Refund Pembatalan"
                :terms="$refundTerms['cancel']"
            />
            <x-tenant.refund-terms
                title="Syarat & Ketentuan Refund Reschedule"
                :terms="$refundTerms['reschedule']"
            />
        </div>

        <div class="mb-8">
            <label for="refund_terms_accepted" class="flex items-start gap-3 rounded-tenant-md border border-gray-200 bg-white p-4 text-sm text-gray-700">
                <input
                    type="checkbox"
                    id="refund_terms_accepted"
                    name="refund_terms_accepted"
                    value="1"
                    required
                    @checked(old('refund_terms_accepted'))
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary focus:ring-primary"
                >
                <span>Saya telah membaca dan menyetujui syarat & ketentuan refund.</span>
            </label>
            @error('refund_terms_accepted')
                <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">

            <x-tenant-card variant="default" class="flex flex-col gap-6">
                <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                    <span class="text-primary">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold text-gray-800">Info Penyewa</h3>
                </div>

                <div class="flex flex-col gap-4">
                    <x-tenant-input
                        name="team_name"
                        label="Nama Tim / Penyewa"
                        placeholder="Masukkan nama tim"
                        required
                    />

                    <div>
                        <x-tenant-input
                            name="tenant_whatsapp"
                            label="Nomor WhatsApp"
                            :value="auth()->user()->phone_number ?? auth()->user()->phone ?? '-'"
                            readonly
                            class="bg-gray-100 text-gray-500 cursor-not-allowed border-gray-200 focus:bg-gray-100 focus:border-gray-200 focus:ring-0"
                        />
                        <p class="text-xs text-gray-400 mt-2 font-medium italic">* Nomor diambil dari data profil Anda.</p>
                    </div>

                    <div>
                        <x-tenant-input
                            name="tenant_email"
                            label="Email"
                            type="email"
                            :value="auth()->user()->email"
                            readonly
                            class="bg-gray-100 text-gray-500 cursor-not-allowed border-gray-200 focus:bg-gray-100 focus:border-gray-200 focus:ring-0"
                        />
                        <p class="text-xs text-gray-400 mt-2 font-medium italic">* Email diambil dari data profil Anda.</p>
                    </div>
                </div>
            </x-tenant-card>

            <x-tenant-card variant="default" class="flex flex-col gap-6">
                <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                    <span class="text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold text-gray-800">Detail Penyewaan</h3>
                </div>

                <div class="p-4 bg-gray-50 border border-gray-200 rounded-tenant-md flex flex-col gap-4">
                    <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                        <span class="text-gray-500 text-sm">Lapangan</span>
                        <span class="font-bold text-gray-800 text-sm">{{ $field->name }}</span>
                    </div>

                    @foreach($groupedSlots as $date => $slots)
                        <div class="flex flex-col border-b border-dashed border-gray-200 last:border-0 pb-2 last:pb-0">
                            <span class="text-gray-700 font-bold text-sm mb-2">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</span>
                            <div class="flex flex-col gap-2">
                                @foreach($slots as $slot)
                                    <div class="flex justify-between items-center bg-white border border-gray-100 px-3 py-2 rounded-tenant-sm">
                                        <span class="font-semibold text-gray-800 text-sm">{{ substr($slot['jam'], 0, 5) }} - {{ substr($slot['jam_akhir'], 0, 5) }}</span>
                                        <span class="text-primary text-sm font-bold">Rp {{ number_format($slot['harga'], 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-gray-800 font-bold text-sm">Total Harga</span>
                        <span class="font-black text-primary text-xl">Rp {{ number_format($totalPrice, 0, ',', '.') }}</span>
                    </div>
                </div>
            </x-tenant-card>

        </div>

        <x-tenant-card variant="default" class="bg-gray-50 mb-8 p-6 md:p-8 flex flex-col gap-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                <div>
                    <h3 class="text-base font-bold text-gray-900 mb-4">Opsi Pembayaran</h3>
                    <div class="flex flex-col gap-3">
                        <label for="pay_dp" class="flex items-center p-4 bg-white border border-gray-200 rounded-tenant-md cursor-pointer hover:border-primary transition-all group relative">
                            <input type="radio" id="pay_dp" name="payment_type" value="down payment" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary cursor-pointer" required>
                            <span class="ml-3 font-semibold text-gray-700 text-sm">DP (Uang Muka 50%)</span>
                            <span class="absolute right-4 font-bold text-primary text-sm">Rp {{ number_format($totalPrice / 2, 0, ',', '.') }}</span>
                        </label>

                        <label for="pay_full" class="flex items-center p-4 bg-white border border-gray-200 rounded-tenant-md cursor-pointer hover:border-primary transition-all group relative">
                            <input type="radio" id="pay_full" name="payment_type" value="final payment" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary cursor-pointer" required>
                            <span class="ml-3 font-semibold text-gray-700 text-sm">Bayar Lunas</span>
                            <span class="absolute right-4 font-bold text-primary text-sm">Rp {{ number_format($totalPrice, 0, ',', '.') }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col h-full">
                    <label for="booking_notes" class="block text-base font-bold text-gray-900 mb-4 cursor-pointer">
                        Catatan Khusus <span class="text-gray-400 font-normal text-sm">(Opsional)</span>
                    </label>
                    <textarea id="booking_notes" name="notes" placeholder="Tuliskan catatan tambahan jika ada..." class="w-full flex-1 h-32 p-4 rounded-tenant-md border border-gray-200 bg-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary resize-none text-sm placeholder-gray-400 transition-all"></textarea>
                </div>

            </div>
        </x-tenant-card>

        <div class="flex justify-end gap-4">
            <x-tenant-button href="javascript:history.back()" variant="danger" class="px-6 py-2.5">
                Kembali
            </x-tenant-button>
            <x-tenant-button type="submit" variant="primary" class="px-6 py-2.5 shadow-tenant-md">
                Lanjutkan Pembayaran
            </x-tenant-button>
        </div>
    </form>
</div>
@endsection
