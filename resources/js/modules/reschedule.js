import Swal from 'sweetalert2';

let selectedBooking = null;
let ui = {};

function resetAllSlotButtons() {
    document.querySelectorAll('.slot-btn').forEach(btn => {
        if (!btn.disabled) {
            btn.className = 'slot-btn px-4 py-3 rounded-tenant-md border border-gray-200 text-gray-700 text-sm font-medium transition-all text-center flex flex-col justify-center cursor-pointer hover:bg-primary-light hover:border-primary';
            btn.querySelector('.price-label')?.classList?.replace('text-blue-100', 'text-gray-500');
        }
    });
}

function setActiveSlotButton(btn) {
    btn.className = 'slot-btn px-4 py-3 rounded-tenant-md border bg-primary text-white border-primary shadow-tenant-sm text-sm font-medium transition-all text-center flex flex-col justify-center cursor-pointer';
    btn.querySelector('.price-label')?.classList?.replace('text-gray-500', 'text-blue-100');
}

function updateSummaryLabels(price, formattedDate, start, end) {
    if (ui.selectedCountLabel) { ui.selectedCountLabel.textContent = '1'; }
    if (ui.totalPriceLabel) { ui.totalPriceLabel.textContent = 'Rp ' + price.toLocaleString('id-ID'); }
    if (ui.summaryDateLabel) { ui.summaryDateLabel.textContent = formattedDate; }
    if (ui.summaryTimeLabel) { ui.summaryTimeLabel.textContent = start?.substring(0, 5) + ' - ' + end?.substring(0, 5); }
}

function handleSlotSelection(btn) {
    resetAllSlotButtons();
    setActiveSlotButton(btn);

    const price = Number.parseInt(btn.dataset?.price ?? '0', 10);
    selectedBooking = {
        date: ui.slotSection?.dataset?.viewDate,
        formattedDate: ui.slotSection?.dataset?.formattedDate,
        start: btn.dataset?.start,
        end: btn.dataset?.end,
        price
    };

    if (ui.playDateInput) { ui.playDateInput.value = selectedBooking.date; }
    if (ui.startTimeInput) { ui.startTimeInput.value = selectedBooking.start; }
    if (ui.endTimeInput) { ui.endTimeInput.value = selectedBooking.end; }

    updateSummaryLabels(price, selectedBooking.formattedDate, selectedBooking.start, selectedBooking.end);
    ui.btnConfirm?.removeAttribute('disabled');
}

function attachSlotListeners() {
    document.querySelectorAll('.slot-btn:not(:disabled)').forEach(btn => {
        btn.onclick = () => handleSlotSelection(btn);
    });
}

function updateDOMContent(doc) {
    const newSlotSection = doc.getElementById('slot-section');
    const newCalendarSection = doc.getElementById('calendar-section');

    if (ui.slotSection && newSlotSection) {
        ui.slotSection.innerHTML = newSlotSection.innerHTML;
        ui.slotSection.dataset.viewDate = newSlotSection.dataset?.viewDate;
        ui.slotSection.dataset.formattedDate = newSlotSection.dataset?.formattedDate;
    }

    if (ui.calendarSection && newCalendarSection) {
        ui.calendarSection.innerHTML = newCalendarSection.innerHTML;
    }
}

function restoreActiveSlotState() {
    if (ui.slotSection?.dataset?.viewDate !== selectedBooking?.date) {
        return;
    }

    const query = `.slot-btn[data-start="${selectedBooking?.start}"][data-end="${selectedBooking?.end}"]:not([disabled])`;
    const activeBtn = document.querySelector(query);

    if (activeBtn) {
        activeBtn.className = 'slot-btn px-4 py-3 rounded-tenant-md border bg-primary text-white border-primary shadow-tenant-sm text-sm font-medium transition-all text-center flex flex-col justify-center cursor-pointer';
        activeBtn.querySelector('.price-label')?.classList?.replace('text-gray-500', 'text-blue-100');
    }
}

async function executeCalendarNavigation(url) {
    if (ui.slotSection) { ui.slotSection.style.opacity = '0.5'; }
    if (ui.calendarSection) { ui.calendarSection.style.opacity = '0.5'; }

    try {
        const response = await fetch(url);
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        updateDOMContent(doc);
        attachSlotListeners();
        restoreActiveSlotState();
    } catch (error) {
        console.error('Reschedule Navigation Failed:', error);
        globalThis.location.href = url;
    } finally {
        if (ui.slotSection) { ui.slotSection.style.opacity = '1'; }
        if (ui.calendarSection) { ui.calendarSection.style.opacity = '1'; }
    }
}

function getReasonValue() {
    const el = document.getElementById('modalReason');
    if (el) {
        if ('value' in el && el.tagName !== 'DIV') {
            return el.value.trim();
        }
        const inner = el.querySelector('textarea, input');
        if (inner) {
            return inner.value.trim();
        }
    }
    const fallback = document.querySelector('#reasonModal textarea, #reasonModal input');
    return fallback ? fallback.value.trim() : '';
}

function handleModalSubmission(submitBtn) {
    const reason = getReasonValue();

    if (!reason) {
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Silakan isi alasan reschedule terlebih dahulu.',
            confirmButtonColor: '#3a5a8c',
            confirmButtonText: 'Mengerti'
        });
        return;
    }

    const inputReason = document.getElementById('inputReason');
    if (inputReason) { 
        inputReason.value = reason; 
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Memproses...';

    const form = document.getElementById('rescheduleForm');
    form?.submit();
}

export function initializeReschedule() {
    const slotSection = document.getElementById('slot-section');
    if (!slotSection) return;

    ui = {
        btnConfirm: document.getElementById('btnConfirm'),
        startTimeInput: document.getElementById('inputStartTime'),
        endTimeInput: document.getElementById('inputEndTime'),
        playDateInput: document.getElementById('inputPlayDate'),
        selectedCountLabel: document.getElementById('selectedCount'),
        totalPriceLabel: document.getElementById('totalPrice'),
        summaryDateLabel: document.getElementById('summaryDate'),
        summaryTimeLabel: document.getElementById('summaryTime'),
        slotSection: slotSection,
        calendarSection: document.getElementById('calendar-section'),
        reasonModal: document.getElementById('reasonModal')
    };

    attachSlotListeners();

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.ajax-link');
        if (link) {
            e.preventDefault();
            executeCalendarNavigation(link.href);
        }
    });

    if (ui.btnConfirm) {
        ui.btnConfirm.onclick = () => {
            if (selectedBooking) {
                ui.reasonModal?.classList?.replace('hidden', 'flex');
                setTimeout(() => {
                    const textarea = document.getElementById('modalReason');
                    if (textarea && 'focus' in textarea) {
                        textarea.focus();
                    }
                }, 50);
            }
        };
    }

    const modalCancel = document.getElementById('modalCancel');
    if (modalCancel) {
        modalCancel.onclick = () => {
            ui.reasonModal?.classList?.replace('flex', 'hidden');
        };
    }

    if (ui.reasonModal) {
        ui.reasonModal.onclick = (e) => {
            if (e.target === ui.reasonModal) {
                ui.reasonModal.classList.replace('flex', 'hidden');
            }
        };
    }

    const modalSubmit = document.getElementById('modalSubmit');
    if (modalSubmit) {
        modalSubmit.onclick = function () {
            handleModalSubmission(this);
        };
    }
}
