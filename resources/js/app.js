document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');

    if (!button) {
        return;
    }

    const input = button.closest('label')?.querySelector('[data-password-input]');

    if (!input) {
        return;
    }

    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.textContent = reveal ? 'Ocultar' : 'Mostrar';
    button.setAttribute('aria-pressed', String(reveal));
});

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    const closer = event.target.closest('[data-dialog-close]');

    if (opener) {
        document.getElementById(opener.dataset.dialogOpen)?.showModal();
    }
    if (closer) {
        closer.closest('dialog')?.close();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.matches?.('dialog[data-dismiss-backdrop]')) event.target.close();
});

const themes = ['system', 'light', 'dark'];

function applyTheme(preference) {
    if (document.querySelector('meta[name="app-public"]')) {
        document.documentElement.classList.remove('dark');
        document.documentElement.dataset.theme = 'light';
        return;
    }

    const dark = preference === 'dark' || (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.theme = preference;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.textContent = preference === 'dark' ? 'Oscuro' : preference === 'light' ? 'Claro' : 'Sistema';
        button.title = `Tema actual: ${button.textContent}`;
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');

    if (button) {
        const current = localStorage.getItem('theme') || 'system';
        const next = themes[(themes.indexOf(current) + 1) % themes.length];
        localStorage.setItem('theme', next);
        applyTheme(next);
    }

    if (event.target.closest('[data-scroll-top]')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if ((localStorage.getItem('theme') || 'system') === 'system') {
        applyTheme('system');
    }
});

window.addEventListener('scroll', () => {
    document.querySelector('[data-scroll-top]')?.classList.toggle('hidden', window.scrollY < 500);
}, { passive: true });

document.addEventListener('DOMContentLoaded', () => applyTheme(localStorage.getItem('theme') || 'system'));

function updateVisibleClocks() {
    document.querySelectorAll('[data-clock-now]').forEach((clock) => {
        clock.textContent = new Intl.DateTimeFormat('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date());
    });
}

setInterval(updateVisibleClocks, 1000);
document.addEventListener('livewire:navigated', updateVisibleClocks);

function initializeProductCarousels() {
    document.querySelectorAll('[data-product-carousel]:not([data-carousel-ready])').forEach((carousel) => {
        carousel.dataset.carouselReady = 'true';
        const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
        const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
        if (slides.length < 2) return;
        let current = 0;
        let timer;

        const show = (index) => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === current;
                slide.classList.toggle('opacity-100', active);
                slide.classList.toggle('opacity-0', !active);
                slide.classList.toggle('pointer-events-none', !active);
                slide.classList.toggle('z-10', active);
                slide.setAttribute('aria-hidden', String(!active));
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('bg-white', dotIndex === current);
                dot.classList.toggle('bg-white/40', dotIndex !== current);
            });
        };
        const stop = () => clearInterval(timer);
        const start = () => {
            stop();
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => show(current + 1), 5500);
        };

        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => { show(current - 1); start(); });
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => { show(current + 1); start(); });
        dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); start(); }));
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', start);
        start();
    });
}

document.addEventListener('DOMContentLoaded', initializeProductCarousels);
document.addEventListener('livewire:navigated', initializeProductCarousels);

function recordPublicAnalytics(eventType, { label = null, productId = null } = {}) {
    const endpoint = document.querySelector('meta[name="analytics-endpoint"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!endpoint || !token) return;
    fetch(endpoint, {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ event_type: eventType, path: `${location.pathname}${location.hash}`, label, product_id: productId }),
    }).catch(() => {});
}

document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-analytics-event], a[href*="#"]');
    if (target) {
        const hash = target.hash?.replace('#', '');
        recordPublicAnalytics('click', { label: target.dataset.analyticsEvent || (hash ? `section_${hash}` : 'enlace_publico') });
    }
});

let productObserver;
function initializeProductAnalytics() {
    productObserver ??= new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting && entry.intersectionRatio >= 0.6).forEach((entry) => {
            const productId = Number(entry.target.dataset.productImpression);
            const key = `product-impression:${productId}:${location.pathname}`;
            if (!sessionStorage.getItem(key)) {
                sessionStorage.setItem(key, '1');
                recordPublicAnalytics('product_view', { label: entry.target.dataset.productName, productId });
            }
            productObserver.unobserve(entry.target);
        });
    }, { threshold: 0.6 });
    document.querySelectorAll('[data-product-impression]:not([data-analytics-ready])').forEach((product) => {
        product.dataset.analyticsReady = 'true';
        productObserver.observe(product);
    });
}
document.addEventListener('DOMContentLoaded', initializeProductAnalytics);
document.addEventListener('livewire:navigated', initializeProductAnalytics);

document.addEventListener('livewire:init', () => {
    if (import.meta.env.VITE_REALTIME_ENABLED !== 'true') return;

    window.Echo.channel('landing').listen('.landing.updated', (event) => {
        document.querySelectorAll('[data-public-reservation-link]').forEach((link) => {
            link.classList.toggle('hidden', !event.reservations_enabled);
        });
        Livewire.dispatch('landing-updated', event);
    });

    if (!document.querySelector('meta[name="app-authenticated"]')) return;

    window.Echo.private('inventory').listen('.inventory.changed', (event) => {
        Livewire.dispatch('inventory-changed', event);
    });
    window.Echo.private('analytics').listen('.analytics.recorded', () => {
        Livewire.dispatch('analytics-recorded');
    });
});

document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-app-menu-open]');
    const closeButton = event.target.closest('[data-app-menu-close]');
    const menu = document.querySelector('[data-app-menu]');

    if (openButton) {
        menu.showModal();
        document.body.classList.add('overflow-hidden');
    }

    if (closeButton) {
        menu.close();
    }

    if (event.target === menu) {
        menu.close();
    }
});

document.querySelector('[data-app-menu]')?.addEventListener('close', () => {
    document.body.classList.remove('overflow-hidden');
});

let barcodeControls = null;

async function stopBarcodeScanner(dialog) {
    if (barcodeControls) {
        barcodeControls.stop();
        barcodeControls = null;
    }
    dialog?.querySelector('[data-barcode-video]')?.srcObject?.getTracks().forEach((track) => track.stop());
}

document.addEventListener('click', async (event) => {
    const openButton = event.target.closest('[data-barcode-open]');
    const closeButton = event.target.closest('[data-barcode-close]');

    if (closeButton) {
        const dialog = closeButton.closest('[data-barcode-dialog]');
        await stopBarcodeScanner(dialog);
        dialog.close();
        return;
    }

    if (!openButton) {
        return;
    }

    const dialog = openButton.nextElementSibling;
    const video = dialog.querySelector('[data-barcode-video]');
    const status = dialog.querySelector('[data-barcode-status]');
    const target = document.getElementById(openButton.dataset.barcodeTarget);
    dialog.showModal();
    status.textContent = 'Activando la cámara…';

    try {
        const { BrowserMultiFormatReader } = await import('@zxing/browser');
        const reader = new BrowserMultiFormatReader();
        barcodeControls = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            video,
            async (result) => {
                if (!result) return;
                target.value = result.getText();
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                status.textContent = `Código leído: ${result.getText()}`;
                await stopBarcodeScanner(dialog);
                setTimeout(() => dialog.close(), 350);
            },
        );
        status.textContent = 'Cámara activa. Acerca el código hasta que se detecte.';
    } catch (error) {
        await stopBarcodeScanner(dialog);
        status.textContent = location.protocol !== 'https:' && location.hostname !== 'localhost'
            ? 'La cámara requiere HTTPS. El lector físico y la búsqueda manual siguen disponibles.'
            : 'No se pudo abrir la cámara. Revisa el permiso del navegador.';
    }
});

document.addEventListener('close', (event) => {
    if (event.target.matches?.('[data-barcode-dialog]')) stopBarcodeScanner(event.target);
}, true);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
