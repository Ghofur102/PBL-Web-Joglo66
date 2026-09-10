@extends('tenant.layouts.app')

@section('title', 'Ubah Jadwal Pemesanan')

@section('content')
    <div class="max-w-6xl mx-auto pb-8">

        <div
            class="bg-amber-50 border border-amber-200 p-4 rounded-tenant-lg mb-6 shadow-tenant-sm border-l-4 border-l-amber-500">
            <div class="flex flex-col gap-2">
                <p class="text-amber-800 text-sm font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    PERINGATAN: ATURAN RESCHEDULE
                </p>
                <ul class="list-disc list-inside text-sm text-amber-700 space-y-1 ml-1 mt-1">
                    <li>Reschedule hanya bisa dilakukan <strong>minimal H-3</strong> sebelum jadwal bermain.</li>
                    <li>Reschedule hanya <strong>1 kali</strong> per sesi booking.</li>
                    <li>Slot lama akan otomatis dibuka setelah reschedule berhasil.</li>
                </ul>
            </div>
        </div>

        <x-tenant-card variant="accent" class="mb-8">
            <div class="flex flex-col gap-2">
                <p class="text-gray-700 text-sm"><span class="font-bold text-primary">Lapangan Dipilih:</span>
                    {{ $detail->booking->field->name }}</p>
                <p class="text-gray-700 text-sm"><span class="font-bold text-primary">Jadwal Lama:</span> <span
                        class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($detail->play_date)->format('d F Y') }}
                        ({{ substr($detail->start_play_time, 0, 5) }} - {{ substr($detail->end_play_time, 0, 5) }})</span>
                </p>
            </div>
        </x-tenant-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">

            <div id="slot-section" data-view-date="{{ $selectedDate }}"
                data-formatted-date="{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}"
                class="bg-white p-6 rounded-tenant-lg border border-gray-200 shadow-tenant-sm transition-opacity duration-300 flex flex-col">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Slot Jam Tersedia</h3>
                <p class="text-sm text-gray-500 mb-5 border-b border-gray-100 pb-4">
                    Tanggal: <span
                        class="font-bold text-primary">{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</span>
                </p>

                @if (count($slots) === 0)
                    <div class="grid grid-cols-2 gap-4">
                        <div
                            class="col-span-2 text-center py-10 text-gray-400 text-sm bg-gray-50 rounded-tenant-md border border-dashed border-gray-300">
                            Tidak ada sesi tersedia untuk tanggal ini.
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4" id="slotGrid">
                        @foreach ($slots as $slot)
                            @php
                                $canSelect = $slot['is_available'];
                                $isPastSlot = \Carbon\Carbon::parse($selectedDate . ' ' . $slot['start'])->isPast();
                                $isOriginal = $slot['is_original'] ?? false;
                                $isClosed = $slot['is_closed'] ?? false;
                                $disabled = !$canSelect || $isPastSlot || $isOriginal;
                            @endphp

                            <button type="button" data-start="{{ $slot['start'] }}" data-end="{{ $slot['end'] }}"
                                data-price="{{ $slot['price'] }}" @if ($disabled) disabled @endif
                                class="slot-btn px-4 py-3 rounded-tenant-md border text-sm font-medium transition-all text-center flex flex-col justify-center
                                       @if ($isOriginal) bg-amber-50 border-amber-300 text-amber-700 cursor-not-allowed
                                       @elseif ($disabled) bg-gray-100 border-gray-200 text-gray-300 cursor-not-allowed
                                       @else bg-white border-gray-200 text-gray-700 cursor-pointer hover:bg-primary-light hover:border-primary @endif">

                                <span class="text-base">{{ substr($slot['start'], 0, 5) }} -
                                    {{ substr($slot['end'], 0, 5) }}</span>
                                <span
                                    class="block text-xs mt-1 price-label @if ($isOriginal) text-amber-600 @elseif($disabled) text-gray-400 @else text-gray-500 @endif">
                                    Rp {{ number_format($slot['price'], 0, ',', '.') }}

                                    @if ($isOriginal)
                                        <span class="font-bold ml-1 block mt-1">&middot; Jadwal Saat Ini</span>
                                    @elseif ($isClosed)
                                        <span class="text-red-500 font-bold ml-1 block mt-1">&middot; Tutup</span>
                                    @elseif (!$canSelect && !$isPastSlot)
                                        <span class="text-red-500 font-bold ml-1 block mt-1">&middot; Terisi</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div id="calendar-section"
                class="bg-white p-6 rounded-tenant-lg border border-gray-200 shadow-tenant-sm h-fit transition-opacity duration-300">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Pilih Tanggal Baru</h3>

                <div class="flex justify-between items-center mb-6">
                    <a href="{{ route('tenant.booking.form.reschedule', ['detail_booking_id' => $detail->id, 'month' => $prevMonth->month, 'year' => $prevMonth->year, 'date' => $selectedDate]) }}"
                        class="ajax-link w-8 h-8 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-tenant-md transition-all font-bold">
                        &lt;
                    </a>
                    <div class="text-base font-bold text-gray-800">
                        {{ Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y') }}
                    </div>
                    <a href="{{ route('tenant.booking.form.reschedule', ['detail_booking_id' => $detail->id, 'month' => $nextMonth->month, 'year' => $nextMonth->year, 'date' => $selectedDate]) }}"
                        class="ajax-link w-8 h-8 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-tenant-md transition-all font-bold">
                        &gt;
                    </a>
                </div>

                <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-bold text-gray-400">
                    <div>Min</div>
                    <div>Sen</div>
                    <div>Sel</div>
                    <div>Rab</div>
                    <div>Kam</div>
                    <div>Jum</div>
                    <div>Sab</div>
                </div>

                <div class="grid grid-cols-7 gap-2 text-center" id="calendarDays">
                    @foreach ($calendar as $cell)
                        @php
                            $isSelected = $cell['date'] === $selectedDate;
                            $linkUrl = route('tenant.booking.form.reschedule', [
                                'detail_booking_id' => $detail->id,
                                'month' => $month,
                                'year' => $year,
                                'date' => $cell['date'],
                            ]);
                        @endphp

                        @if (!$cell['isCurrentMonth'] || $cell['isPast'])
                            <div class="w-8 h-8 flex items-center justify-center mx-auto text-xs text-gray-300 font-medium">
                                {{ $cell['day'] }}
                            </div>
                        @elseif ($isSelected)
                            <a href="{{ $linkUrl }}"
                                class="ajax-link w-8 h-8 flex items-center justify-center mx-auto text-xs bg-primary text-white rounded-tenant-full font-bold shadow-tenant-sm transition-all">
                                {{ $cell['day'] }}
                            </a>
                        @elseif ($cell['isToday'])
                            <a href="{{ $linkUrl }}"
                                class="ajax-link w-8 h-8 flex items-center justify-center mx-auto text-xs text-primary font-bold border border-primary rounded-tenant-full hover:bg-primary-light transition-all">
                                {{ $cell['day'] }}
                            </a>
                        @else
                            <a href="{{ $linkUrl }}"
                                class="ajax-link w-8 h-8 flex items-center justify-center mx-auto text-xs text-gray-700 font-medium hover:bg-primary-light rounded-tenant-full transition-all">
                                {{ $cell['day'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <x-tenant-card variant="default">
            <div class="bg-primary-light border border-primary/10 p-4 rounded-tenant-md mb-6 border-l-4 border-l-primary">
                <h4 class="font-bold text-gray-800 mb-3">Ringkasan Pilihan Anda</h4>

                <div class="flex flex-col md:flex-row gap-6 md:gap-16">
                    <div>
                        <div class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Slot Dipilih</div>
                        <div class="text-lg font-bold text-primary" id="selectedCount">0</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Total Harga Baru</div>
                        <div class="text-lg font-bold text-primary" id="totalPrice">Rp 0</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Jadwal Pengganti</div>
                        <div class="text-sm font-bold text-primary" id="summaryDate">Belum dipilih</div>
                        <div class="text-sm font-bold text-primary" id="summaryTime">-</div>
                    </div>
                </div>
            </div>

            <form id="rescheduleForm" method="POST" action="{{ route('tenant.booking.confirmation.reschedule') }}">
                @csrf
                <input type="hidden" name="detail_booking_id" value="{{ $detail->id }}">
                <input type="hidden" name="new_play_date" id="inputPlayDate">
                <input type="hidden" name="new_start_play_time" id="inputStartTime">
                <input type="hidden" name="new_end_play_time" id="inputEndTime">
                <input type="hidden" name="reason" id="inputReason">

                <div class="flex justify-end gap-4 mt-6">
                    <x-tenant-button :href="route('tenant.booking.history.show', $detail->fk_booking_id)" variant="danger" class="px-6 py-2.5">
                        Kembali
                    </x-tenant-button>
                    <x-tenant-button type="button" id="btnConfirm" disabled variant="primary"
                        class="px-6 py-2.5 shadow-tenant-sm">
                        Lanjut Konfirmasi
                    </x-tenant-button>
                </div>
            </form>
        </x-tenant-card>
    </div>

    <div id="reasonModal"
        class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 backdrop-blur-xs transition-all duration-300">
        <div class="bg-white rounded-tenant-lg p-6 max-w-md w-full mx-4 shadow-tenant-md border border-gray-100">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Alasan Perubahan</h3>
            <p class="text-sm text-gray-500 mb-4">Tuliskan alasan mengapa Anda ingin mengubah jadwal ini:</p>

            <div class="mb-6">
                <label for="modalReason" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Alasan Perubahan Jadwal <span class="text-red-500">*</span>
                </label>
                <textarea id="modalReason" rows="4" required
                    placeholder="Contoh: Ada urusan mendadak..."
                    class="w-full px-4 py-2.5 rounded-tenant-md border border-gray-300 focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm text-gray-800 transition-all outline-none resize-none"></textarea>
            </div>

            <div class="flex gap-4 justify-end">
                <x-tenant-button type="button" id="modalCancel"
                    class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-5 py-2.5">
                    Batal
                </x-tenant-button>
                <x-tenant-button type="button" id="modalSubmit" variant="primary" class="px-6 py-2.5 shadow-tenant-sm">
                    Proses Reschedule
                </x-tenant-button>
            </div>
        </div>
    </div>
@endsection
