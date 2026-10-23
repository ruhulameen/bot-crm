import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.store('toasts', {
    items: [],
    next: 1,

    add(type, message, timeout = type === 'error' ? 8000 : 4000) {
        const id = this.next++;

        this.items.push({ id, type, message });
        setTimeout(() => this.remove(id), timeout);
    },

    remove(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

Alpine.store('confirm', {
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    variant: 'primary',
    resolver: null,

    /** Opens the dialog; resolves to true when confirmed, false when cancelled. */
    ask({ title = 'Please confirm', message = '', confirmLabel = 'Confirm', variant = 'primary' } = {}) {
        this.resolver?.(false);
        Object.assign(this, { open: true, title, message, confirmLabel, variant });

        return new Promise((resolve) => {
            this.resolver = resolve;
        });
    },

    answer(confirmed) {
        this.open = false;
        this.resolver?.(confirmed);
        this.resolver = null;
    },
});

/**
 * Copies text to the clipboard. The Clipboard API only exists on HTTPS and localhost, so fall back to a hidden
 * textarea everywhere else (e.g. a plain-HTTP .test domain). Resolves to whether the copy worked.
 */
window.copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // fall through to the legacy method
        }
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }
    area.remove();

    return copied;
};

// $copy(text): copy with feedback; a failure tells the admin to copy by hand instead of failing silently.
Alpine.magic('copy', () => async (text) => {
    const copied = await window.copyText(text);

    if (!copied) {
        Alpine.store('toasts').add('error', 'Copy failed. Select the text and copy it manually.');
    }

    return copied;
});

Alpine.start();
