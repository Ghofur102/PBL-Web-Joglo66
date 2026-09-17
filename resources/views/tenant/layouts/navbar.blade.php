<nav class="bg-primary shadow-tenant-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <div class="shrink-0 flex items-center">
                <a href="{{ route('tenant.booking.dashboard') }}" class="text-white text-xl font-bold tracking-wide">Joglo66</a>
            </div>

            <div class="hidden md:flex md:items-center md:space-x-4">
                <a href="{{ route('tenant.booking.dashboard') }}" class="text-white hover:bg-primary-dark px-3 py-2 rounded-tenant-md text-sm font-medium transition">Booking Lapangan</a>
                <a href="{{ route('tenant.booking.transaction') }}" class="text-white hover:bg-primary-dark px-3 py-2 rounded-tenant-md text-sm font-medium transition">Riwayat Transaksi</a>

                <a href="{{ route('tenant.notifications.index') }}" class="relative text-white hover:bg-primary-dark p-2 rounded-tenant-md transition" aria-label="Notifikasi">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    @if(auth()->check() && auth()->user()->unreadNotifications->count() > 0)
                        <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {{ auth()->user()->unreadNotifications->count() > 99 ? '99+' : auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('profile.show') }}" class="text-white hover:bg-primary-dark px-3 py-2 rounded-tenant-md text-sm font-medium transition">Profil</a>

                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-red-200 hover:bg-red-900/40 px-3 py-2 rounded-tenant-md text-sm font-medium transition cursor-pointer">Keluar</button>
                </form>
            </div>

            <div class="flex items-center space-x-2 md:hidden">
                <a href="{{ route('tenant.notifications.index') }}" class="relative text-white p-2 rounded-tenant-md hover:bg-primary-dark transition">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    @if(auth()->check() && auth()->user()->unreadNotifications->count() > 0)
                        <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {{ auth()->user()->unreadNotifications->count() > 99 ? '99+' : auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </a>

                <button type="button" id="mobile-menu-button" class="inline-flex items-center justify-center p-2 rounded-tenant-md text-white hover:bg-primary-dark focus:outline-none transition" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Menu Utama</span>
                    <svg class="block h-6 w-6" id="icon-menu-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg class="hidden h-6 w-6" id="icon-menu-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="hidden md:hidden bg-primary-dark border-t border-primary-hover" id="mobile-menu">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="{{ route('tenant.booking.dashboard') }}" class="block text-white hover:bg-primary px-3 py-2 rounded-tenant-md text-base font-medium transition">Booking Lapangan</a>
            <a href="{{ route('tenant.booking.transaction') }}" class="block text-white hover:bg-primary px-3 py-2 rounded-tenant-md text-base font-medium transition">Riwayat Transaksi</a>
            <a href="{{ route('profile.show') }}" class="block text-white hover:bg-primary px-3 py-2 rounded-tenant-md text-base font-medium transition">Profil</a>

            <form action="{{ route('logout') }}" method="POST" class="block w-full">
                @csrf
                <button type="submit" class="block w-full text-left text-red-200 hover:bg-red-900/40 px-3 py-2 rounded-tenant-md text-base font-medium transition cursor-pointer">Keluar</button>
            </form>
        </div>
    </div>
</nav>
