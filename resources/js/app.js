import Alpine from 'alpinejs';
import { animate } from 'motion';

window.Alpine = Alpine;

function motionAllowed() {
    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function enter(el, delay = 0) {
    if (!motionAllowed() || !el) {
        return;
    }

    animate(
        el,
        { opacity: [0, 1], transform: ['translateY(14px)', 'none'] },
        { duration: 0.28, ease: 'easeOut', delay },
    );
}

Alpine.data('ballotFlow', (total) => ({
    step: 0,
    total,
    needChoice: false,
    init() {
        this.$watch('step', () => this.reveal());
        this.reveal();
    },
    reveal() {
        this.needChoice = false;
        enter(this.$refs.panel);
    },
    hasChoice() {
        return Boolean(this.$root.querySelector(`fieldset[data-step="${this.step}"] input:checked`));
    },
    next() {
        if (!this.hasChoice() || this.step >= this.total - 1) {
            this.needChoice = !this.hasChoice();
            return;
        }

        this.step += 1;
    },
    prev() {
        if (this.step === 0) {
            return;
        }

        this.step -= 1;
    },
}));

Alpine.start();

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-enter]').forEach((el, index) => {
        enter(el, index * 0.05);
    });
});
