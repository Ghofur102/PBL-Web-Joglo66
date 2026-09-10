let formUi = {};
let reviewUi = {};

function getTextareaElement() {
    const el = document.getElementById('cancelReason');
    if (el) {
        if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
            return el;
        }
        const inner = el.querySelector('textarea, input');
        if (inner) return inner;
    }
    return document.querySelector('textarea');
}

function updateSubmitState() {
    const textarea = getTextareaElement();
    const reasonValue = (formUi.reasonHidden?.value || textarea?.value || '').trim();
    const hasReason = reasonValue.length > 0;
    const isAgreed = formUi.agreeCheck?.checked ?? false;

    if (formUi.reasonHidden && hasReason) {
        formUi.reasonHidden.value = reasonValue;
    }

    if (formUi.btnSubmit) {
        if (hasReason && isAgreed) {
            formUi.btnSubmit.removeAttribute('disabled');
            formUi.btnSubmit.disabled = false;
        } else {
            formUi.btnSubmit.setAttribute('disabled', 'true');
            formUi.btnSubmit.disabled = true;
        }
    }
}

function handleRadioChange(radio) {
    if (!radio.checked) return;

    const textarea = getTextareaElement();
    const val = radio.value;

    if (val === 'Alasan Lainnya') {
        if (textarea) {
            textarea.value = '';
            textarea.focus();
        }
        if (formUi.reasonHidden) {
            formUi.reasonHidden.value = '';
        }
    } else {
        if (textarea) {
            textarea.value = val;
        }
        if (formUi.reasonHidden) {
            formUi.reasonHidden.value = val;
        }
    }

    updateSubmitState();
}

function handleTextareaInput(value) {
    if (formUi.reasonHidden) {
        formUi.reasonHidden.value = value;
    }

    if (formUi.radioButtons) {
        const matchingRadio = Array.from(formUi.radioButtons).find(rb => rb.value === value.trim());
        if (matchingRadio) {
            matchingRadio.checked = true;
        } else {
            const otherRadio = Array.from(formUi.radioButtons).find(rb => rb.value === 'Alasan Lainnya');
            if (otherRadio && value.trim().length > 0) {
                otherRadio.checked = true;
            }
        }
    }

    updateSubmitState();
}

function toggleNotesContent() {
    if (!reviewUi.notesContent || !reviewUi.toggleNotesBtn) return;

    const isHidden = reviewUi.notesContent.classList.toggle('hidden');
    reviewUi.toggleNotesBtn.textContent = isHidden
        ? 'Catatan kebijakan pembatalan'
        : 'Sembunyikan catatan kebijakan pembatalan';
}

export function initializeCancelForm() {
    const cancelForm = document.getElementById('cancelForm');
    if (!cancelForm) return;

    formUi = {
        reasonTextarea: getTextareaElement(),
        reasonHidden: document.getElementById('inputReason'),
        agreeCheck: document.getElementById('agreeCheck'),
        btnSubmit: document.getElementById('btnSubmit'),
        radioButtons: document.querySelectorAll('input[name="reason_option"]')
    };

    formUi.radioButtons.forEach(rb => {
        rb.addEventListener('change', () => handleRadioChange(rb));
    });

    if (formUi.reasonTextarea) {
        formUi.reasonTextarea.addEventListener('input', function () {
            handleTextareaInput(this.value);
        });
    }

    if (formUi.agreeCheck) {
        formUi.agreeCheck.addEventListener('change', () => updateSubmitState());
    }

    if (formUi.btnSubmit) {
        formUi.btnSubmit.addEventListener('click', (e) => {
            e.preventDefault();
            const textarea = getTextareaElement();
            const reason = (formUi.reasonHidden?.value || textarea?.value || '').trim();

            if (!reason || !formUi.agreeCheck?.checked) {
                updateSubmitState();
                return;
            }

            if (formUi.reasonHidden) {
                formUi.reasonHidden.value = reason;
            }

            cancelForm.submit();
        });
    }

    updateSubmitState();
}

export function initializeCancelReview() {
    const toggleNotesBtn = document.getElementById('toggleNotes');
    if (!toggleNotesBtn) return;

    reviewUi = {
        toggleNotesBtn: toggleNotesBtn,
        notesContent: document.getElementById('notesContent')
    };

    reviewUi.toggleNotesBtn.onclick = () => toggleNotesContent();
}
