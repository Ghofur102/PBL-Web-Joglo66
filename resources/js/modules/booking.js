const MONTH_NAMES = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

function getLocalDateFormat(date) {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getPriceColor(isSelected, finalStatus) {
    if (isSelected) {
        return 'text-blue-100';
    }
    if (finalStatus === 'kosong') {
        return 'text-gray-500';
    }
    return 'text-gray-400';
}

function determineSlotStatus(slot, isToday, now) {
    if (!isToday || slot.status !== 'kosong') {
        return slot.status;
    }
    const [slotHour, slotMinute] = slot.jam.split(':').map(Number);
    if (slotHour < now.getHours() || (slotHour === now.getHours() && slotMinute <= now.getMinutes())) {
        return 'lewat';
    }
    return 'kosong';
}

function getStatusLabel(finalStatus) {
    if (finalStatus === 'terisi' || finalStatus === 'tutup') {
        return `<span class="text-red-500 font-bold block">&middot; ${finalStatus.toUpperCase()}</span>`;
    }
    if (finalStatus === 'lewat') {
        return '<span class="text-gray-400 font-bold block">&middot; LEWAT</span>';
    }
    return '';
}

function renderPrevMonthEmptyDays(container, firstDay, daysInPrevMonth) {
    for (let i = firstDay - 1; i >= 0; i--) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'calendar-day empty text-transparent';
        emptyDiv.textContent = daysInPrevMonth - i;
        container.appendChild(emptyDiv);
    }
}

function createDayCell(ctx, fullDate, day, today) {
    const dateStr = getLocalDateFormat(fullDate);
    const dayDiv = document.createElement('div');
    dayDiv.classList.add('calendar-day');
    dayDiv.textContent = day;

    if (fullDate < today) {
        dayDiv.classList.add('past');
        return dayDiv;
    }

    if (fullDate.getTime() === today.getTime()) dayDiv.classList.add('today');
    if (ctx.selectedDate && fullDate.getTime() === new Date(ctx.selectedDate).setHours(0, 0, 0, 0)) {
        dayDiv.classList.add('selected');
    }

    const hasSlot = ctx.selectedSlots.some(s => s.date === dateStr);
    const isNotCurrentlySelected = !ctx.selectedDate || fullDate.getTime() !== new Date(ctx.selectedDate).setHours(0, 0, 0, 0);

    if (hasSlot && isNotCurrentlySelected) {
        dayDiv.className = 'calendar-day bg-primary-light text-primary ring-1 ring-primary font-bold';
    }

    dayDiv.onclick = () => selectDate(ctx, fullDate);
    return dayDiv;
}

function renderCurrentMonthDays(ctx, container, year, month, daysInMonth) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let day = 1; day <= daysInMonth; day++) {
        const fullDate = new Date(year, month, day);
        fullDate.setHours(0, 0, 0, 0);

        const dayCell = createDayCell(ctx, fullDate, day, today);
        container.appendChild(dayCell);
    }
}

function renderNextMonthEmptyDays(container) {
    const remaining = 42 - container.children.length;
    for (let i = 1; i <= remaining; i++) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'calendar-day empty text-transparent';
        emptyDiv.textContent = i;
        container.appendChild(emptyDiv);
    }
}

function renderCalendar(ctx) {
    const year = ctx.currentDate.getFullYear();
    const month = ctx.currentDate.getMonth();

    const monthEl = document.getElementById('calendarMonth');
    if (monthEl) monthEl.textContent = `${MONTH_NAMES[month]} ${year}`;

    const calendarDays = document.getElementById('calendarDays');
    if (!calendarDays) return;
    calendarDays.innerHTML = '';

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    renderPrevMonthEmptyDays(calendarDays, firstDay, daysInPrevMonth);
    renderCurrentMonthDays(ctx, calendarDays, year, month, daysInMonth);
    renderNextMonthEmptyDays(calendarDays);
}

function selectDate(ctx, date) {
    ctx.selectedDate = date;
    renderCalendar(ctx);

    const dateStr = getLocalDateFormat(date);

    const formBookingDate = document.getElementById('formBookingDate');
    if (formBookingDate) formBookingDate.value = dateStr;

    const slotGrid = document.getElementById('slotGrid');
    if (!slotGrid) return;

    slotGrid.innerHTML = '<div class="col-span-2 text-center py-8 text-gray-500 bg-gray-50 rounded-tenant-md animate-pulse border border-dashed border-gray-300 text-sm">Memuat slot...</div>';

    fetch(`${ctx.fetchUrl}?field_id=${ctx.fieldId}&date=${dateStr}`)
        .then(res => res.json())
        .then(data => {
            ctx.allSlots = data.slots || [];
            renderSlotJam(ctx);
        })
        .catch(() => {
            slotGrid.innerHTML = '<div class="col-span-2 text-center py-8 text-red-500 text-sm">Gagal memuat jadwal slot.</div>';
        });
}

function handleSlotClick(ctx, slot, dateStr) {
    const existingIdx = ctx.selectedSlots.findIndex(s => s.date === dateStr && s.jam === slot.jam);
    if (existingIdx > -1) {
        ctx.selectedSlots.splice(existingIdx, 1);
    } else {
        ctx.selectedSlots.push({
            date: dateStr,
            jam: slot.jam,
            jam_akhir: slot.jam_akhir,
            harga: slot.harga
        });
    }
    renderSlotJam(ctx);
    renderCalendar(ctx);
    updateSelectedInfo(ctx);
}

function createSlotButton(ctx, slot, finalStatus, isSelected, dateStr) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'slot-pill flex flex-col justify-center items-center gap-1';

    if (finalStatus === 'kosong') {
        btn.classList.add(isSelected ? 'selected' : 'kosong');
        btn.onclick = () => handleSlotClick(ctx, slot, dateStr);
    } else {
        btn.classList.add(finalStatus);
        btn.disabled = true;
    }

    const statusLabel = getStatusLabel(finalStatus);
    const priceColor = getPriceColor(isSelected, finalStatus);

    btn.innerHTML = `
        <span class="block">${slot.jam.substring(0, 5)} - ${slot.jam_akhir.substring(0, 5)}</span>
        <span class="block text-xs font-medium ${priceColor}">
            Rp ${new Intl.NumberFormat('id-ID').format(slot.harga)}
            ${statusLabel}
        </span>
    `;
    return btn;
}

function renderSlotJam(ctx) {
    const slotGrid = document.getElementById('slotGrid');
    if (!slotGrid) return;
    slotGrid.innerHTML = '';

    if (ctx.allSlots.length === 0) {
        slotGrid.innerHTML = `
            <div class="col-span-2 text-center py-8 text-gray-400 text-sm bg-gray-50 rounded-tenant-md border border-dashed border-gray-300">
                Belum ada jadwal slot atau tarif yang tersedia untuk tanggal ini.
            </div>
        `;
        return;
    }

    const dateStr = getLocalDateFormat(ctx.selectedDate);
    const now = new Date();
    const isToday = ctx.selectedDate.toDateString() === now.toDateString();

    ctx.allSlots.forEach((slot) => {
        const finalStatus = determineSlotStatus(slot, isToday, now);
        const isSelected = ctx.selectedSlots.some(s => s.date === dateStr && s.jam === slot.jam);
        const btn = createSlotButton(ctx, slot, finalStatus, isSelected, dateStr);
        slotGrid.appendChild(btn);
    });
}

function updateSelectedInfo(ctx) {
    const infoEl = document.getElementById('selectedInfo');
    const submitBtnTarget = document.getElementById('submitBtn');
    const formSelectedSlots = document.getElementById('formSelectedSlots');
    const hasSlots = ctx.selectedSlots.length > 0;

    let submitBtn = null;
    if (submitBtnTarget) {
        submitBtn = submitBtnTarget.tagName === 'BUTTON'
            ? submitBtnTarget
            : submitBtnTarget.querySelector('button');
    }

    if (infoEl) {
        if (hasSlots) {
            const uniqueDates = new Set(ctx.selectedSlots.map(s => s.date)).size;
            infoEl.textContent = `${ctx.selectedSlots.length} Slot di ${uniqueDates} Hari Berbeda`;
        } else {
            infoEl.textContent = 'Belum dipilih';
        }
    }

    if (submitBtn) {
        submitBtn.disabled = !hasSlots;
    }

    if (formSelectedSlots) {
        formSelectedSlots.value = JSON.stringify(hasSlots ? ctx.selectedSlots : []);
    }

    const total = ctx.selectedSlots.reduce((sum, s) => sum + s.harga, 0);
    const countEl = document.getElementById('selectedCount');
    const priceEl = document.getElementById('totalPrice');

    if (countEl) countEl.textContent = ctx.selectedSlots.length;
    if (priceEl) priceEl.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
}

export function initializeBookingApp() {
    const appData = document.getElementById('booking-app');
    if (!appData) return;

    const ctx = {
        fieldId: appData.dataset.fieldId,
        fetchUrl: appData.dataset.fetchUrl,
        currentDate: new Date(),
        selectedDate: null,
        allSlots: [],
        selectedSlots: []
    };

    const prevBtn = document.getElementById('btnPrevMonth');
    if (prevBtn) {
        prevBtn.onclick = () => {
            ctx.currentDate.setMonth(ctx.currentDate.getMonth() - 1);
            renderCalendar(ctx);
        };
    }

    const nextBtn = document.getElementById('btnNextMonth');
    if (nextBtn) {
        nextBtn.onclick = () => {
            ctx.currentDate.setMonth(ctx.currentDate.getMonth() + 1);
            renderCalendar(ctx);
        };
    }

    const initialToday = new Date();
    initialToday.setHours(0, 0, 0, 0);

    renderCalendar(ctx);
    selectDate(ctx, initialToday);
}
