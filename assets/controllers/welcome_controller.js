import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['embers'];
    static values = { dismissAfter: { type: Number, default: 4200 } };

    connect() {
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!this.reduced && this.hasEmbersTarget) {
            this.spawnEmbers();
        }

        this.dismissTimer = setTimeout(() => this.dismiss(), this.reduced ? 1200 : this.dismissAfterValue);
    }

    disconnect() {
        clearTimeout(this.dismissTimer);
    }

    spawnEmbers() {
        const frag = document.createDocumentFragment();
        for (let i = 0; i < 14; i++) {
            const span = document.createElement('span');
            span.style.setProperty('--x', Math.round(Math.random() * 100));
            span.style.setProperty('--d', `${(Math.random() * 3).toFixed(2)}s`);
            frag.appendChild(span);
        }
        this.embersTarget.appendChild(frag);
    }

    dismiss() {
        clearTimeout(this.dismissTimer);
        this.element.setAttribute('data-dismissing', '');
        setTimeout(() => this.element.remove(), 500);
    }
}
