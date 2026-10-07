// Mekanik shell modal bersama (overlay, scroll lock, fokus, dismiss).
// Konten domain, copy, dan efek-samping close tetap milik tiap konsumen.
// Modul ini tanpa side-effect: hanya export, tidak self-boot.
//
// Catatan Tailwind: kelas overlay (mis. z-[80]) dioper sebagai literal
// dari pemanggil agar pemindai build tetap menemukan kandidat CSS.

const trapTab = (modal, event) => {
    const focusableElements = Array.from(modal.querySelectorAll('a[href]:not([hidden]), button:not([hidden])'));
    if (focusableElements.length === 0) return;

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
};

const createModalShell = ({ id, labelledby = null, overlayClass = 'z-[80]' }) => {
    const modal = document.createElement('div');
    modal.id = id;
    modal.hidden = true;
    modal.className = `fixed inset-0 ${overlayClass} flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm`;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    if (labelledby) modal.setAttribute('aria-labelledby', labelledby);
    document.body.appendChild(modal);

    return modal;
};

const openModalShell = (modal, focusSelector = null) => {
    modal.hidden = false;
    document.body.classList.add('overflow-hidden');
    if (focusSelector) {
        window.requestAnimationFrame(() => modal.querySelector(focusSelector)?.focus());
    }
};

const closeModalShell = (modal) => {
    modal.hidden = true;
    document.body.classList.remove('overflow-hidden');
};

const wireModalDismiss = (modal, { closeSelector, onClose, trapFocus = false }) => {
    modal.querySelectorAll(closeSelector).forEach((button) => {
        button.addEventListener('click', onClose);
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) onClose();
    });
    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            onClose();
            return;
        }

        if (trapFocus && event.key === 'Tab') trapTab(modal, event);
    });
};

export { createModalShell, openModalShell, closeModalShell, wireModalDismiss };
