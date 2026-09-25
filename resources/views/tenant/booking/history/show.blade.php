@extends('tenant.layouts.app')

@section('title', 'Detail Transaksi')

@section('content')
    <div class="w-full pb-12 mt-4">

        <x-tenant-card variant="flat" class="overflow-hidden mb-8">
            <div class="bg-gray-50 border-b border-gray-100 p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 -mx-4 -mt-4 mb-6">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Detail Pemesanan
                    </h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-2 font-mono">
                        No. Ref: <span class="font-bold text-gray-800">{{ $booking->mainPayment->reference_id ?? 'Menunggu Pembayaran' }}</span>
                    </p>
                </div>

                <div class="flex flex-col items-start md:items-end gap-2">
                    <span class="px-3 py-1 rounded-tenant-full text-xs font-bold border {{ $booking->badgeClass }} uppercase tracking-wider">
                        Status: {{ $booking->overallStatus }}
                    </span>
                    <p class="text-gray-400 text-xs">Dibuat: {{ $booking->created_at->format('d M Y, H:i') }} WIB</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-gray-50/50 rounded-tenant-lg p-5 border border-gray-100 flex flex-col justify-between">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Informasi Pemesan</h3>
                    <div class="space-y-4 text-sm flex-1">
                        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Nama Tim</span><span class="font-semibold text-gray-900 text-right">{{ $booking->team_name }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">No. HP</span><span class="font-medium text-gray-900 text-right">{{ $booking->customer_phone ?? '-' }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Email</span><span class="font-medium text-gray-900 text-right break-all">{{ $booking->customer_email ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="bg-gray-50/50 rounded-tenant-lg p-5 border border-gray-100 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Informasi Lapangan</h3>
                        <div class="space-y-4 text-sm">
                            <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Lapangan</span><span class="font-semibold text-gray-900 text-right">{{ $booking->field->name ?? '-' }}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Tgl Induk</span><span class="font-medium text-gray-900 text-right">{{ \Carbon\Carbon::parse($booking->booking_date)->format('d F Y') }}</span></div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200/60">
                        <span class="block text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Catatan Tambahan:</span>
                        <div class="text-sm font-medium text-gray-800 bg-white p-3 rounded-tenant-md border border-gray-100 break-words whitespace-pre-wrap min-h-12">
                            {{ $booking->notes ?: 'Tidak ada catatan khusus dari penyewa.' }}
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Rincian Sesi Disewa
            </h3>

            <div class="overflow-x-auto mb-8 border border-gray-200 rounded-tenant-lg shadow-tenant-sm">
                <table class="w-full text-left text-sm whitespace-nowrap min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-gray-500 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-semibold">Tanggal Main</th>
                            <th class="px-6 py-4 font-semibold">Sesi Jam</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Harga Sesi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($booking->details as $detail)
                            <tr class="hover:bg-gray-50/50 transition-colors {{ $detail->isCancelled ? 'opacity-60 bg-red-50/30' : ($detail->hasPendingCancellation || $detail->hasPendingReschedule ? 'bg-amber-50/20' : '') }}">
                                <td class="px-6 py-4 text-gray-900">
                                    {{ \Carbon\Carbon::parse($detail->play_date)->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 text-gray-900 font-medium">
                                    {{ \Carbon\Carbon::parse($detail->start_play_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($detail->end_play_time)->format('H:i') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-tenant-full text-xs font-semibold border {{ $detail->detailBadge }} capitalize">
                                        {{ $detail->detailStatus }}
                                    </span>
                                    @if (in_array($detail->status, ['field closure', 'closed field cancelled', 'closed field reschedule'], true))
                                        <span class="ml-1 inline-flex px-2.5 py-1 rounded-tenant-full text-xs font-bold bg-red-100 text-red-700 border border-red-200">
                                            Sesi Ditutup
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-semibold {{ $detail->isCancelled ? 'line-through text-gray-400' : 'text-gray-900' }} text-right">
                                    Rp {{ number_format($detail->price, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 mb-8">
                <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    Detail Sesi & Alur Histori Perubahan
                </h3>

                <div class="space-y-6">
                    @foreach ($booking->details as $index => $detail)
                        <div class="bg-white border border-gray-200 rounded-tenant-lg shadow-tenant-sm p-6">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center pb-4 border-b border-gray-100 gap-3">
                                <div>
                                    <span class="text-xs font-bold text-primary tracking-wider uppercase">Sesi #{{ $index + 1 }}</span>
                                    <h4 class="text-base font-bold text-gray-900 mt-0.5">
                                        {{ \Carbon\Carbon::parse($detail->play_date)->format('d F Y') }}
                                        <span class="text-sm font-normal text-gray-500">({{ substr($detail->start_play_time, 0, 5) }} – {{ substr($detail->end_play_time, 0, 5) }} WIB)</span>
                                    </h4>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="px-3 py-1 rounded-tenant-full text-xs font-bold border {{ $detail->detailBadge }} uppercase">
                                        {{ $detail->detailStatus }}
                                    </span>
                                    @if (in_array($detail->status, ['field closure', 'closed field cancelled', 'closed field reschedule'], true))
                                        <span class="px-3 py-1.5 rounded-tenant-md text-xs font-bold bg-red-100 text-red-700 border border-red-200">
                                            Sesi Ditutup
                                        </span>
                                    @endif
                                    @if ($detail->hasPendingCancellation)
                                        <span class="px-3 py-1.5 rounded-tenant-md text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            Pengajuan Batal Diproses
                                        </span>
                                    @elseif ($detail->hasPendingReschedule)
                                        <span class="px-3 py-1.5 rounded-tenant-md text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            Pengajuan Reschedule Diproses
                                        </span>
                                    @else
                                        @if ($detail->canReschedule)
                                            <x-tenant-button :href="route('tenant.booking.form.reschedule', ['detail_booking_id' => $detail->id])"
                                                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 text-xs">
                                                Reschedule
                                            </x-tenant-button>
                                        @endif
                                        @if ($detail->canCancel)
                                            <x-tenant-button :href="route('tenant.booking.form.cancelled', ['detail_booking_id' => $detail->id])" variant="danger"
                                                class="px-4 py-2 text-xs">
                                                Batalkan
                                            </x-tenant-button>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <div class="relative pl-6 mt-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
                                <div class="relative">
                                    <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white shadow-sm"></div>
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                        <p class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Tahap 1: Pemesanan Awal</p>
                                        <span class="text-xs text-gray-400">{{ $booking->created_at->format('d M Y, H:i') }} WIB</span>
                                    </div>
                                    <p class="text-sm text-gray-700 mt-1">
                                        Jadwal dipesan: <strong>{{ \Carbon\Carbon::parse($detail->reschedules->where('approval_status', 'approved')->last()->old_date ?? $detail->play_date)->format('d F Y') }}</strong>
                                        (Harga Sesi Awal: Rp {{ number_format($detail->initialPrice, 0, ',', '.') }})
                                    </p>
                                    <div class="mt-2 inline-flex items-center gap-2 px-3 py-1.5 rounded-tenant-md bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-medium">
                                        <span>Dana Masuk (Pembayaran Awal):</span>
                                        <strong class="text-emerald-700 text-sm font-bold">+ Rp {{ number_format($detail->initialPaid, 0, ',', '.') }}</strong>
                                    </div>
                                </div>

                                @if ($detail->reschedules && $detail->reschedules->count() > 0)
                                    @foreach ($detail->reschedules as $rsc)
                                        @if ($rsc->approval_status === 'pending')
                                            <div class="relative">
                                                <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-amber-400 border-2 border-white shadow-sm"></div>
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">
                                                        Pengajuan Reschedule (Menunggu Konfirmasi Admin)
                                                    </p>
                                                    <span class="text-xs text-gray-400">{{ $rsc->created_at->format('d M Y, H:i') }} WIB</span>
                                                </div>
                                                <div class="bg-amber-50/80 border border-amber-200 rounded-tenant-md p-3.5 text-xs text-amber-900 mt-2 space-y-1.5">
                                                    <p>
                                                        Jadwal Saat Ini: <span class="font-semibold text-amber-800">{{ \Carbon\Carbon::parse($rsc->old_date)->format('d F Y') }}</span>
                                                        &rarr; Usulan Jadwal Baru: <strong class="text-amber-950">{{ \Carbon\Carbon::parse($rsc->new_play_date)->format('d F Y') }} ({{ substr($rsc->new_start_play_time, 0, 5) }} – {{ substr($rsc->new_end_play_time, 0, 5) }} WIB)</strong>
                                                    </p>
                                                    <p>Alasan Pemesan: <em>“{{ $rsc->reason }}”</em></p>
                                                    <p>Perkiraan Nilai Slot Baru: Rp {{ number_format($rsc->new_price ?? $detail->price, 0, ',', '.') }}</p>
                                                    <div class="pt-2 border-t border-amber-200/60 flex items-center gap-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-200 text-amber-900">
                                                            Status: Menunggu Persetujuan Pengelola
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif ($rsc->approval_status === 'rejected')
                                            <div class="relative">
                                                <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-rose-400 border-2 border-white shadow-sm"></div>
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                    <p class="text-xs font-bold text-rose-700 uppercase tracking-wider">
                                                        Pengajuan Reschedule Ditolak
                                                    </p>
                                                    <span class="text-xs text-gray-400">{{ $rsc->created_at->format('d M Y, H:i') }} WIB</span>
                                                </div>
                                                <div class="bg-rose-50 border border-rose-200 rounded-tenant-md p-3 text-xs text-rose-900 mt-2 space-y-1">
                                                    <p>Usulan Jadwal Baru: <strong>{{ \Carbon\Carbon::parse($rsc->new_play_date)->format('d F Y') }}</strong> (Ditolak)</p>
                                                    <p>Alasan Penolakan: <em>“{{ $rsc->rejection_reason ?? 'Slot atau jadwal tidak disetujui pengelola.' }}”</em></p>
                                                    <p class="text-[11px] text-rose-600 font-medium">Jadwal bermain tetap berjalan sesuai jadwal aktif saat ini.</p>
                                                </div>
                                            </div>
                                        @else
                                            <div class="relative">
                                                <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-white shadow-sm"></div>
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">
                                                        Tahap: Jadwal Diubah (Reschedule)
                                                    </p>
                                                    <span class="text-xs text-gray-400">{{ $rsc->created_at->format('d M Y, H:i') }} WIB</span>
                                                </div>
                                                <div class="bg-amber-50/70 border border-amber-200/80 rounded-tenant-md p-3 text-xs text-amber-900 mt-2 space-y-1.5">
                                                    <p>
                                                        Jadwal Lama: <span class="font-semibold line-through text-amber-800">{{ \Carbon\Carbon::parse($rsc->old_date)->format('d F Y') }}</span>
                                                        &rarr; Jadwal Baru: <strong class="text-amber-950">{{ \Carbon\Carbon::parse($detail->play_date)->format('d F Y') }}</strong>
                                                    </p>
                                                    <p>Alasan Pemesan: <em>“{{ $rsc->reason }}”</em></p>
                                                    <p>Perubahan Nilai Slot: Rp {{ number_format($rsc->oldPrice, 0, ',', '.') }} &rarr; Rp {{ number_format($rsc->newPrice, 0, ',', '.') }}</p>

                                                    <div class="pt-2 border-t border-amber-200/60 flex flex-wrap items-center gap-2">
                                                        @if ($rsc->adjustmentType === 'refund')
                                                            <span class="font-bold text-orange-800">Penyesuaian (Lebih Bayar):</span>
                                                            <span class="px-2.5 py-1 rounded bg-orange-100 text-orange-800 font-bold text-xs">
                                                                Dana Refund: - Rp {{ number_format($rsc->diffAmount, 0, ',', '.') }}
                                                            </span>
                                                            <span class="text-[11px] text-orange-600">(Status: Hak Pengembalian Kasir)</span>
                                                        @elseif ($rsc->adjustmentType === 'fee')
                                                            <span class="font-bold text-amber-800">Penyesuaian (Kurang Bayar):</span>
                                                            <span class="px-2.5 py-1 rounded bg-amber-100 text-amber-900 font-bold text-xs">
                                                                Dana Masuk Tambahan: + Rp {{ number_format($rsc->diffAmount, 0, ',', '.') }}
                                                            </span>
                                                            <span class="text-[11px] text-amber-700">(Status: Tagihan Tambahan Kasir)</span>
                                                        @else
                                                            <span class="text-gray-600 italic">Harga slot baru sama (Rp 0 selisih biaya).</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif

                                @if ($detail->cancellation)
                                    @if ($detail->cancellation->approval_status === 'pending')
                                        <div class="relative">
                                            <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-white shadow-sm"></div>
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">
                                                    Pengajuan Pembatalan (Menunggu Konfirmasi Admin)
                                                </p>
                                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($detail->cancellation->cancle_date)->format('d M Y') }}</span>
                                            </div>
                                            <div class="bg-amber-50 border border-amber-200 rounded-tenant-md p-3.5 text-xs text-amber-900 mt-2 space-y-2">
                                                <p>Alasan Pembatalan: <em>“{{ $detail->cancellation->reason }}”</em></p>
                                                <p>Skema Pengembalian: <strong class="capitalize">{{ $detail->cancellation->status_refund }}</strong></p>
                                                <div class="pt-2 border-t border-amber-200/70 flex items-center justify-between">
                                                    <span class="text-[11px] text-amber-800 font-medium">Permohonan pembatalan Anda sedang ditinjau oleh pihak pengelola.</span>
                                                    <span class="px-2.5 py-1 rounded bg-amber-200 text-amber-900 font-bold text-[11px]">Menunggu Persetujuan</span>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif ($detail->cancellation->approval_status === 'rejected')
                                        <div class="relative">
                                            <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-rose-500 border-2 border-white shadow-sm"></div>
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                <p class="text-xs font-bold text-rose-700 uppercase tracking-wider">
                                                    Pengajuan Pembatalan Ditolak
                                                </p>
                                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($detail->cancellation->cancle_date)->format('d M Y') }}</span>
                                            </div>
                                            <div class="bg-rose-50 border border-rose-200 rounded-tenant-md p-3.5 text-xs text-rose-900 mt-2 space-y-1.5">
                                                <p>Alasan Pengajuan: <em>“{{ $detail->cancellation->reason }}”</em></p>
                                                <p>Alasan Penolakan: <strong class="text-rose-950">{{ $detail->cancellation->rejection_reason ?? 'Ditolak oleh admin pengelola.' }}</strong></p>
                                                <p class="text-[11px] text-rose-600 font-medium pt-1">Jadwal sesi tetap aktif dan dana pemesanan tidak dibatalkan.</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="relative">
                                            <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-red-500 border-2 border-white shadow-sm"></div>
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                                <p class="text-xs font-bold text-red-700 uppercase tracking-wider">
                                                    Tahap Akhir: Sesi Dibatalkan (Cancel)
                                                </p>
                                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($detail->cancellation->cancle_date)->format('d M Y') }}</span>
                                            </div>
                                            <div class="bg-red-50 border border-red-200 rounded-tenant-md p-3.5 text-xs text-red-900 mt-2 space-y-2">
                                                <p>Alasan Pembatalan: <em>“{{ $detail->cancellation->reason }}”</em></p>
                                                <p>Skema Kebijakan: <strong class="capitalize">{{ $detail->cancellation->status_refund }}</strong></p>

                                                <div class="pt-2 border-t border-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                    <div>
                                                        <span class="text-gray-600">Total Akumulasi Dana Masuk Sesi:</span>
                                                        <strong class="text-gray-900 font-bold">Rp {{ number_format($detail->totalDanaMasukSesi, 0, ',', '.') }}</strong>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-green-800 font-bold">Dana Refund Dikembalikan:</span>
                                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded font-black text-sm">
                                                            Rp {{ number_format($detail->refundAmount, 0, ',', '.') }}
                                                        </span>
                                                    </div>
                                                </div>

                                                @if ($detail->danaHangus > 0)
                                                    <p class="text-red-700 font-semibold text-right">
                                                        Dana Hangus Masuk Kas: Rp {{ number_format($detail->danaHangus, 0, ',', '.') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                @if (!$detail->isCancelled)
                                    <div class="relative">
                                        <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-blue-500 border-2 border-white shadow-sm"></div>
                                        <p class="text-xs font-bold text-blue-700 uppercase tracking-wider">Status: Sesi Aktif</p>
                                        <p class="text-xs text-gray-500 mt-1">Sesi terjadwal bermain dan siap digunakan sesuai jam yang tertera.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="w-full text-sm bg-gray-50/50 p-6 rounded-tenant-lg border border-gray-200 shadow-tenant-sm mt-8">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Rincian Pembayaran</h3>

                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Metode Pembayaran</span>
                        <span class="font-bold text-gray-900 uppercase">{{ $booking->mainPayment->method ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Tipe Pembayaran</span>
                        <span class="font-bold text-gray-900 capitalize">{{ $booking->mainPayment->payment_type ?? 'Menunggu' }}</span>
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-1"></div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Tagihan Sesi Aktif</span>
                        <span class="font-bold text-gray-900">Rp {{ number_format($booking->tagihanAktif, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Total Dana Masuk</span>
                        <span class="font-bold text-emerald-600">+ Rp {{ number_format($booking->uangMasuk, 0, ',', '.') }}</span>
                    </div>

                    @if ($booking->uangRefund > 0)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 font-medium">Total Dana Dikembalikan (Refund)</span>
                            <span class="font-bold text-orange-500">- Rp {{ number_format($booking->uangRefund, 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200">
                    <span class="text-lg font-bold text-gray-900">Sisa Tagihan</span>

                    @if (in_array($booking->overallStatus, ['cancelled', 'failed', 'expired']) && $booking->sisaTagihan == 0)
                        <span class="px-4 py-2 bg-gray-100 text-gray-500 rounded-tenant-md font-black tracking-widest uppercase text-sm border border-gray-200 shadow-tenant-sm">
                            DIBATALKAN
                        </span>
                    @elseif($booking->sisaTagihan == 0)
                        <span class="px-4 py-2 bg-green-100 text-green-700 rounded-tenant-md font-black tracking-widest uppercase text-sm border border-green-200 shadow-tenant-sm">
                            LUNAS
                        </span>
                    @else
                        <span class="text-2xl font-black text-red-500 tracking-tight">
                            Rp {{ number_format($booking->sisaTagihan, 0, ',', '.') }}
                        </span>
                    @endif
                </div>
            </div>
        </x-tenant-card>
    </div>
@endsection
