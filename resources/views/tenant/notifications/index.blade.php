@extends('tenant.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Notifikasi</h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">Daftar pemberitahuan pemesanan, pengajuan jadwal ulang, dan pembatalan sewa.</p>
        </div>
        @if($unreadCount > 0)
            <form action="{{ route('tenant.notifications.readAll') }}" method="POST">
                @csrf
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-tenant-md text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition">
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="mt-4 p-3 sm:p-4 rounded-tenant-md bg-green-50 border border-green-200 text-xs sm:text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mt-6">
        @php
            $groupedNotifications = $notifications->getCollection()->groupBy(function($item) {
                if ($item->created_at->isToday()) {
                    return 'Hari Ini';
                } elseif ($item->created_at->isYesterday()) {
                    return 'Kemarin';
                }
                return $item->created_at->isoFormat('D MMMM Y');
            });
        @endphp

        @forelse($groupedNotifications as $dateHeader => $items)
            <div class="mb-6">
                <div class="sticky top-16 z-10 my-3 flex items-center">
                    <span class="bg-gray-100/95 backdrop-blur-sm border border-gray-200 text-gray-600 text-[11px] font-bold px-3 py-1 rounded-full shadow-sm">
                        {{ $dateHeader }}
                    </span>
                    <div class="flex-grow border-t border-gray-200 ml-3"></div>
                </div>

                <div class="space-y-3">
                    @foreach($items as $notification)
                        @php
                            $isUnread = is_null($notification->read_at);
                            $type = $notification->data['type'] ?? 'info';
                            $bookingDetailId = $notification->data['booking_detail_id'] ?? null;
                            $senderName = $notification->data['sender_name'] ?? 'Sistem';
                            $senderRole = $notification->data['sender_role'] ?? 'system';
                        @endphp
                        <div class="p-3.5 sm:p-4 rounded-tenant-md border transition {{ $isUnread ? 'bg-blue-50/70 border-blue-200 shadow-sm' : 'bg-white border-gray-200' }}">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                <div class="flex items-start space-x-3 min-w-0">
                                    <div class="mt-0.5 shrink-0">
                                        @if(str_contains($type, 'cancel'))
                                            <span class="inline-flex p-2 rounded-full bg-red-100 text-red-600">
                                                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </span>
                                        @elseif(str_contains($type, 'reschedule'))
                                            <span class="inline-flex p-2 rounded-full bg-amber-100 text-amber-600">
                                                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </span>
                                        @else
                                            <span class="inline-flex p-2 rounded-full bg-blue-100 text-blue-600">
                                                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-xs sm:text-sm font-bold text-gray-900 break-words">{{ $notification->data['title'] ?? 'Pemberitahuan' }}</h2>
                                            @if($isUnread)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-600 text-white">Baru</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs sm:text-sm text-gray-600 leading-relaxed break-words">{{ $notification->data['message'] ?? '' }}</p>

                                        <div class="mt-2 flex flex-wrap items-center gap-x-2 sm:gap-x-3 gap-y-1 text-[11px] text-gray-400">
                                            <span>Dari: <strong class="text-gray-600 font-semibold">{{ $senderName }}</strong> ({{ ucfirst($senderRole) }})</span>
                                            <span>&bull;</span>
                                            <span>{{ $notification->created_at->format('H:i') }} WIB</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 self-end sm:self-center mt-2 sm:mt-0 flex flex-wrap justify-end gap-2">
                                    @if($type === 'field_closure_by_admin' && $bookingDetailId)
                                        <a href="{{ route('tenant.booking.form.reschedule', ['detail_booking_id' => $bookingDetailId]) }}" class="inline-flex items-center px-3 py-1.5 rounded-tenant-md text-xs font-semibold text-white bg-primary hover:bg-primary-dark transition">
                                            Reschedule
                                        </a>
                                        <a href="{{ route('tenant.booking.form.cancelled', ['detail_booking_id' => $bookingDetailId]) }}" class="inline-flex items-center px-3 py-1.5 rounded-tenant-md text-xs font-semibold text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 transition">
                                            Cancel
                                        </a>
                                    @endif
                                    <a href="{{ route('tenant.notifications.read', $notification->id) }}" class="inline-flex items-center px-3 py-1.5 rounded-tenant-md text-xs font-semibold text-primary bg-primary/5 hover:bg-primary/10 transition">
                                        Rincian &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-white rounded-tenant-md border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                <h3 class="mt-3 text-sm font-semibold text-gray-900">Belum Ada Notifikasi</h3>
                <p class="mt-1 text-xs text-gray-500">Semua riwayat konfirmasi dan perubahan status akan ditampilkan di sini.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
