@extends('tenant.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>
            <p class="text-sm text-gray-500 mt-1">Daftar pemberitahuan pemesanan, pengajuan jadwal ulang, dan pembatalan sewa.</p>
        </div>
        @if($unreadCount > 0)
            <form action="{{ route('tenant.notifications.readAll') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-tenant-md text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition">
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="mt-4 p-4 rounded-tenant-md bg-green-50 border border-green-200 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mt-6 space-y-3">
        @forelse($notifications as $notification)
            @php
                $isUnread = is_null($notification->read_at);
                $type = $notification->data['type'] ?? 'info';
                $senderName = $notification->data['sender_name'] ?? 'Sistem';
                $senderRole = $notification->data['sender_role'] ?? 'system';
            @endphp
            <div class="p-4 rounded-tenant-md border transition {{ $isUnread ? 'bg-blue-50/60 border-blue-200 shadow-sm' : 'bg-white border-gray-200' }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start space-x-3">
                        <div class="mt-0.5 shrink-0">
                            @if($type === 'cancel_request' || $type === 'cancel_rejected')
                                <span class="inline-flex p-2 rounded-full bg-red-100 text-red-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </span>
                            @elseif($type === 'reschedule_request' || $type === 'reschedule_approved')
                                <span class="inline-flex p-2 rounded-full bg-amber-100 text-amber-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </span>
                            @else
                                <span class="inline-flex p-2 rounded-full bg-blue-100 text-blue-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                </span>
                            @endif
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-900">{{ $notification->data['title'] ?? 'Pemberitahuan' }}</h2>
                                @if($isUnread)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-blue-600 text-white">Baru</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600 leading-relaxed">{{ $notification->data['message'] ?? '' }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400">
                                <span>Dari: <strong class="text-gray-600 font-medium">{{ $senderName }}</strong> ({{ ucfirst($senderRole) }})</span>
                                <span>&bull;</span>
                                <span>{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 self-center">
                        <a href="{{ route('tenant.notifications.read', $notification->id) }}" class="inline-flex items-center px-3 py-1.5 rounded-tenant-md text-xs font-medium text-primary hover:bg-primary/10 transition">
                            Lihat Rincian
                        </a>
                    </div>
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
