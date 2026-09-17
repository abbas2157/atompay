import Alpine from 'alpinejs';

/** Mirrors App\Support\Money::format(). */
export const pkr = (n) => 'PKR ' + Math.round(n).toLocaleString('en-PK');

/**
 * Landing-page plan calculator. Mirrors InstalmentQuoteService::quote() so
 * the on-page estimate and the server quote never disagree. `config` is
 * InstalmentQuoteService::clientConfig() printed by the view.
 */
Alpine.data('planCalculator', (config, initial = {}) => ({
    tenures: config.tenures,
    price: initial.price ?? 100000,
    months: initial.months ?? config.tenures[0],

    get advance()  { return Math.ceil(this.price * config.minAdvance); },
    get financed() { return this.price - this.advance; },
    get markup()   { return Math.round((config.perMonth * this.months / 100) * this.financed); },
    get total()    { return this.price + this.markup; },
    get monthly()  { return Math.ceil((this.total - this.advance) / this.months); },

    tenureIndex: 0,
    init() {
        this.tenureIndex = Math.max(0, this.tenures.indexOf(this.months));
        this.$watch('tenureIndex', (i) => (this.months = this.tenures[i]));
    },

    fmt: pkr,
    monthsLabel() { return this.months + (this.months === 1 ? ' month' : ' months'); },
}));

/**
 * Instant 30% / 10% estimate shown while the user types, before the form
 * posts to the server (which is the only place a limit is recorded).
 */
Alpine.data('incomeEstimate', (ratios, initialIncome = null) => ({
    income: initialIncome,
    get valid() { return Number(this.income) >= 1000; },
    get limit() { return this.valid ? Math.round(this.income * ratios.limit) : 0; },
    get maxInstalment() { return this.valid ? Math.round(this.income * ratios.instalment) : 0; },
    fmt: pkr,
}));

/**
 * Multi-step application wizard. One <form>, every step stays in the DOM
 * (x-show), so a single POST carries all fields and server validation
 * remains the source of truth. `state` mirrors the inputs for the review
 * step; `errorStep` (from the server) opens the step holding the first error.
 */
Alpine.data('applicationWizard', ({ steps, initial, ratios, errorStep = null, labels = {} }) => ({
    steps,
    current: errorStep ?? 0,
    furthest: errorStep ?? 0,
    form: { ...initial },
    files: {},               // input name -> { name, url } for previews
    consent: false,
    labels,

    get isFirst() { return this.current === 0; },
    get isLast()  { return this.current === this.steps.length - 1; },
    get progress() { return Math.round((this.current / (this.steps.length - 1)) * 100); },

    /** Native validation for the fields inside the current step only. */
    validateStep() {
        const panel = this.$root.querySelector(`[data-step="${this.current}"]`);
        const fields = [...panel.querySelectorAll('input, select, textarea')];
        const invalid = fields.find((f) => !f.disabled && f.offsetParent !== null && !f.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            invalid.focus();
            return false;
        }
        return true;
    },
    next() {
        if (!this.validateStep()) return;
        this.current = Math.min(this.current + 1, this.steps.length - 1);
        this.furthest = Math.max(this.furthest, this.current);
        this.scrollTop();
    },
    back() { this.current = Math.max(this.current - 1, 0); this.scrollTop(); },
    go(i) { if (i <= this.furthest) { this.current = i; this.scrollTop(); } },
    scrollTop() { this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' }); },

    /** Preview a chosen image and remember its name for the review step. */
    pick(event) {
        const input = event.target;
        const file = input.files?.[0];
        if (this.files[input.name]?.url) URL.revokeObjectURL(this.files[input.name].url);
        this.files[input.name] = file
            ? { name: file.name, url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null }
            : null;
    },

    label(field) { return this.labels[field]?.[this.form[field]] ?? this.form[field] ?? '—'; },
    fmt: pkr,
    get disposable() { return (Number(this.form.monthly_income) || 0) - (Number(this.form.existing_instalments) || 0) - (Number(this.form.monthly_expenses) || 0); },
    get hasEmployer() { return ['salaried', 'self_employed', 'business_owner'].includes(this.form.employment_status); },
    get incomeValid() { return Number(this.form.monthly_income) >= 1000; },
    get limit() { return this.incomeValid && this.disposable > 0 ? Math.round(this.form.monthly_income * ratios.limit) : 0; },
    get maxInstalment() { return this.incomeValid ? Math.max(0, Math.min(Math.round(this.form.monthly_income * ratios.instalment), this.disposable)) : 0; },
}));

window.Alpine = Alpine;
Alpine.start();
