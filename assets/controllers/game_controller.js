import { Controller } from '@hotwired/stimulus';

const FIELD_DEFS = [
    { key: 'name', id: 'guess-name' },
    { key: 'releaseYear', id: 'guess-year' },
    { key: 'publisher', id: 'guess-publisher' },
    { key: 'protagonist', id: 'guess-protagonist' },
];

const LEVEL_MAX_FIELDS = { 1: 1, 2: 2, 3: FIELD_DEFS.length };
const ACTIVE_CLASSES = ['border-primary', 'bg-primary/10', 'text-primary'];
const FUZZY_FIELDS = ['name', 'publisher', 'protagonist'];
const FUZZY_MIN_LENGTH = 3;

function normalize(value) {
    return String(value)
        .trim()
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function isCorrectGuess(fieldKey, rawGuess, rawTarget) {
    const guess = normalize(rawGuess);
    const target = normalize(rawTarget);
    if (guess.length === 0) return false;
    if (guess === target) return true;
    if (FUZZY_FIELDS.includes(fieldKey) && guess.length >= FUZZY_MIN_LENGTH) {
        return target.includes(guess) || guess.includes(target);
    }
    return false;
}

const DEFAULT_LEVEL = 2;
const DEFAULT_ROUNDS = 10;

export default class extends Controller {
    static targets = [
        'setup', 'levelButton', 'roundsButton', 'startButton',
        'boot', 'bootLine', 'bootBar',
        'game', 'roundCounter', 'targetLabel', 'image',
        'form', 'result', 'submitButton',
        'finish',
    ];

    connect() {
        this.selectedLevel = DEFAULT_LEVEL;
        this.selectedRounds = DEFAULT_ROUNDS;
        this.manches = [];
        this.currentManche = 0;
        this.scoreTotal = 0;
        this.visibleFields = [];

        this.setActive(this.levelButtonTargets, (btn) => parseInt(btn.dataset.level, 10) === this.selectedLevel);
        this.setActive(this.roundsButtonTargets, (btn) => parseInt(btn.dataset.rounds, 10) === this.selectedRounds);
    }

    setActive(buttons, matcher) {
        buttons.forEach((btn) => {
            btn.classList.remove(...ACTIVE_CLASSES);
            if (matcher(btn)) btn.classList.add(...ACTIVE_CLASSES);
        });
    }

    selectLevel(event) {
        this.selectedLevel = parseInt(event.currentTarget.dataset.level, 10);
        this.setActive(this.levelButtonTargets, (btn) => btn === event.currentTarget);
    }

    selectRounds(event) {
        this.selectedRounds = parseInt(event.currentTarget.dataset.rounds, 10);
        this.setActive(this.roundsButtonTargets, (btn) => btn === event.currentTarget);
    }

    start() {
        this.setupTarget.classList.add('hidden');
        this.bootTarget.classList.remove('hidden');
        this.startGame();
    }

    runBootSequence() {
        return new Promise((resolve) => {
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const totalDuration = reduced ? 150 : 1500;
            const lines = this.bootLineTargets;
            const bar = this.bootBarTarget;
            const perLine = totalDuration / lines.length;
            const lineDuration = reduced ? 40 : 420;

            bar.style.transitionProperty = 'width';
            bar.style.transitionDuration = totalDuration + 'ms';
            bar.style.transitionTimingFunction = 'linear';
            requestAnimationFrame(() => { bar.style.width = '100%'; });

            lines.forEach((line, i) => {
                setTimeout(() => {
                    lines.forEach((l) => l.removeAttribute('data-active'));
                    line.setAttribute('data-active', '');
                }, i * perLine);
                setTimeout(() => {
                    line.setAttribute('data-done', '');
                    line.removeAttribute('data-active');
                }, i * perLine + lineDuration);
            });

            setTimeout(resolve, totalDuration + 150);
        });
    }

    showBootError(message) {
        this.bootTarget.innerHTML = `<p class="text-center text-destructive font-semibold">${message}</p>`;
    }

    async startGame() {
        let fetchError = null;
        const bootPromise = this.runBootSequence();
        const fetchPromise = fetch(`/api/games?limit=${this.selectedRounds}`)
            .then((r) => r.json())
            .then((data) => { this.manches = data; })
            .catch((err) => { fetchError = err; });

        await Promise.all([bootPromise, fetchPromise]);

        if (fetchError) {
            this.showBootError('Erreur de chargement de l\'API.');
            console.error(fetchError);
            return;
        }
        if (this.manches.length === 0) {
            this.showBootError('Oups, aucun jeu dans la base ! Ajoutes-en via EasyAdmin.');
            return;
        }

        this.currentManche = 0;
        this.scoreTotal = 0;

        this.bootTarget.classList.add('hidden');
        this.gameTarget.classList.remove('hidden');
        this.gameTarget.classList.add('motion-safe:animate-in', 'motion-safe:fade-in', 'motion-safe:slide-in-from-bottom-2', 'duration-500');

        this.loadCurrentManche();
    }

    loadCurrentManche() {
        const game = this.manches[this.currentManche];
        const availableFields = FIELD_DEFS.filter((f) => game[f.key] !== null && game[f.key] !== undefined && game[f.key] !== '');
        this.visibleFields = availableFields.slice(0, LEVEL_MAX_FIELDS[this.selectedLevel] ?? availableFields.length);

        this.roundCounterTarget.textContent = `Manche ${this.currentManche + 1}/${this.manches.length}`;
        this.targetLabelTarget.textContent = `TARGET_${String(this.currentManche + 1).padStart(2, '0')}.IMG`;

        const img = this.imageTarget;
        img.style.opacity = '0';
        setTimeout(() => {
            img.src = game.imageUrl;
            img.onload = () => { img.style.opacity = '1'; };
        }, 150);

        FIELD_DEFS.forEach((f, i) => {
            const row = this.formTarget.querySelector(`.field-row[data-field="${f.key}"]`);
            const input = row.querySelector('input');
            const status = row.querySelector('.field-status');
            const answer = row.querySelector('.field-answer');
            const visible = this.visibleFields.includes(f);

            row.classList.toggle('hidden', !visible);
            row.classList.toggle('flex', visible);
            row.classList.remove('border-green-500/40', 'border-destructive/50');
            row.classList.add('border-border');
            input.value = '';
            input.disabled = false;
            status.textContent = '';
            status.classList.remove('scale-100', 'opacity-100', 'text-green-500', 'dark:text-green-400', 'text-destructive');
            status.classList.add('scale-0', 'opacity-0');
            answer.textContent = '';
            answer.classList.add('hidden');

            if (visible) {
                row.classList.remove('motion-safe:animate-in', 'motion-safe:fade-in');
                void row.offsetWidth;
                row.style.animationDelay = `${i * 60}ms`;
                row.classList.add('motion-safe:animate-in', 'motion-safe:fade-in');
            }
        });

        this.submitButtonTarget.disabled = false;

        this.resultTarget.classList.add('hidden');
        this.resultTarget.innerHTML = '';
    }

    submitGuess(event) {
        event.preventDefault();
        const game = this.manches[this.currentManche];
        let correct = 0;

        this.visibleFields.forEach((f) => {
            const row = this.formTarget.querySelector(`.field-row[data-field="${f.key}"]`);
            const input = row.querySelector('input');
            const status = row.querySelector('.field-status');
            const answer = row.querySelector('.field-answer');
            const ok = isCorrectGuess(f.key, input.value, game[f.key]);

            if (ok) correct++;
            input.disabled = true;
            row.classList.remove('border-border');
            row.classList.add(ok ? 'border-green-500/40' : 'border-destructive/50');
            status.textContent = ok ? '✓' : '✕';
            status.classList.remove('scale-0', 'opacity-0');
            if (!ok) {
                answer.textContent = `Réponse : ${game[f.key]}`;
                answer.classList.remove('hidden');
            }
            status.classList.add('scale-100', 'opacity-100', ok ? 'text-green-500' : 'text-destructive');
            if (ok) status.classList.add('dark:text-green-400');
        });

        this.submitButtonTarget.disabled = true;

        const pointsManche = correct * 100;
        this.scoreTotal += pointsManche;

        const result = this.resultTarget;
        result.classList.remove('hidden');
        result.innerHTML = `<span class="font-mono text-sm text-muted-foreground">SCORE&nbsp;: <span class="text-primary font-bold text-lg">${pointsManche}</span> / ${this.visibleFields.length * 100}</span>`;

        this.currentManche++;

        if (this.currentManche < this.manches.length) {
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'inline-flex h-9 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium transition-colors hover:bg-muted hover:border-primary hover:text-primary';
            nextBtn.textContent = 'Manche suivante →';
            nextBtn.addEventListener('click', () => this.loadCurrentManche());
            result.appendChild(nextBtn);
        } else {
            setTimeout(() => this.showFinish(), 1200);
        }
    }

    async showFinish() {
        this.gameTarget.classList.add('hidden');
        this.finishTarget.classList.remove('hidden');
        this.finishTarget.innerHTML = `
            <div class="rounded-2xl border border-border bg-card/60 backdrop-blur-sm shadow-2xl p-10 text-center motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 duration-500">
                <p class="font-mono text-xs uppercase tracking-widest text-primary mb-2">&gt; Mission terminée</p>
                <h2 class="text-3xl font-black uppercase tracking-tight mb-6">Partie terminée</h2>
                <div class="inline-flex items-center justify-center rounded-full bg-primary/10 w-32 h-32 border-4 border-primary/20 shadow-[0_0_30px_rgba(220,38,38,0.2)] mb-4">
                    <span class="text-4xl font-black text-primary">${this.scoreTotal}</span>
                </div>
                <p class="text-muted-foreground mb-6">points cumulés.</p>
                <div id="save-status" class="flex justify-center mb-6">
                    <svg class="h-8 w-8 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <a href="/" class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-8 text-sm font-medium transition-colors hover:bg-muted hover:text-foreground">
                    Retour au classement
                </a>
            </div>
        `;

        try {
            const response = await fetch('/api/score', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ points: this.scoreTotal }),
            });

            const saveStatus = document.getElementById('save-status');
            if (response.ok) {
                saveStatus.innerHTML = `<span class="inline-flex items-center px-4 py-2 rounded-md bg-green-500/20 text-green-600 dark:text-green-400 font-medium"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Score sauvegardé !</span>`;
            } else {
                saveStatus.innerHTML = `<span class="inline-flex items-center px-4 py-2 rounded-md bg-destructive/20 text-destructive font-medium">Erreur lors de la sauvegarde.</span>`;
            }
        } catch (error) {
            console.error('Erreur lors de la sauvegarde du score', error);
        }
    }
}
