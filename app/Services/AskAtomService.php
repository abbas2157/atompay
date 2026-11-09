<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Ask Atom, AtomPay's side of the assistant.
 *
 * Rules only, as AtomShop's does by default (ATOM_AI_DRIVER=rules): built-in
 * answers, free, no key, no personal data leaving the server. It answers the
 * questions AtomPay actually owns - the plan, the limit, eligibility, the
 * application, payments, the apps - and hands anything about buying, delivery
 * or a product's price to atomshop.pk, which is where those live.
 *
 * Every figure comes from the same services the pages use, so Atom can never
 * quote a plan or a limit that the rest of the site disagrees with.
 */
class AskAtomService
{
    public function __construct(
        private readonly InstalmentQuoteService $quotes,
        private readonly CatalogueService $catalogue,
    ) {}

    /**
     * @return array{answer: string, actions: array<int, array{label: string, url: string}>, chips: array<int, string>}
     */
    public function answer(string $question): array
    {
        $q = Str::lower(trim($question));

        foreach ($this->rules() as $rule) {
            foreach ($rule['match'] as $needle) {
                if (str_contains($q, $needle)) {
                    return $this->shape($rule['reply']($q));
                }
            }
        }

        return $this->shape($this->fallback());
    }

    /** The questions offered before anything is typed. */
    public function chips(): array
    {
        return [
            'What does AtomPay cost?',
            'How do I get a limit?',
            'Which products can I buy?',
            'When are my payments due?',
        ];
    }

    /* --------------------------------------------------------------- rules */

    /**
     * Ordered: the first matching rule wins, so put the specific before the
     * general. Every `reply` returns [html, actions].
     *
     * @return array<int, array{match: array<int, string>, reply: callable}>
     */
    private function rules(): array
    {
        return [
            // Cost, markup, interest, fees - the question most people open with.
            [
                'match' => ['cost', 'markup', 'interest', 'profit', 'extra', 'fee', 'charge', 'expensive', 'how much more'],
                'reply' => fn () => $this->cost(),
            ],

            // A plan for a specific price: "plan for 50000", "60,000".
            [
                'match' => ['plan for', 'monthly on', 'per month on', 'instalment on', 'installment on'],
                'reply' => fn (string $q) => $this->planFor($q),
            ],

            [
                'match' => ['how long', 'term', 'tenure', 'months', 'duration', '6 month', '12 month', 'longer'],
                'reply' => fn () => $this->term(),
            ],

            [
                'match' => ['advance', 'down payment', 'downpayment', 'upfront', 'deposit'],
                'reply' => fn () => $this->advance(),
            ],

            [
                'match' => ['limit', 'how much can i', 'spend', 'income', 'salary', 'eligib'],
                'reply' => fn (string $q) => str_contains($q, 'product') || str_contains($q, 'which')
                    ? $this->eligibleProducts()
                    : $this->limit(),
            ],

            [
                'match' => ['apply', 'application', 'kyc', 'cnic', 'selfie', 'document', 'verif', 'address visit', 'how do i start', 'get started'],
                'reply' => fn () => $this->apply(),
            ],

            [
                'match' => ['product', 'phone', 'mobile', 'tv', 'laptop', 'buy', 'shop', 'what can i buy', 'catalogue', 'catalog'],
                'reply' => fn () => $this->eligibleProducts(),
            ],

            [
                'match' => ['pay', 'payment', 'due', 'late', 'miss', 'reminder', 'schedule', 'instalment date', 'installment date'],
                'reply' => fn () => $this->payments(),
            ],

            [
                'match' => ['app', 'android', 'iphone', 'ios', 'play store', 'app store', 'download'],
                'reply' => fn () => $this->apps(),
            ],

            [
                'match' => ['account', 'sign in', 'login', 'log in', 'password', 'register', 'sign up'],
                'reply' => fn () => $this->account(),
            ],

            [
                'match' => ['delivery', 'deliver', 'ship', 'warranty', 'return', 'refund', 'cancel order'],
                'reply' => fn () => $this->shopSide(),
            ],

            [
                'match' => ['help', 'contact', 'support', 'whatsapp', 'phone number', 'speak to', 'human'],
                'reply' => fn () => $this->support(),
            ],

            [
                'match' => ['what is atompay', 'who are you', 'atompay?', 'about atompay', 'difference'],
                'reply' => fn () => $this->whatIsIt(),
            ],
        ];
    }

    /* ------------------------------------------------------------- answers */

    private function cost(): array
    {
        $plan = $this->quotes->atompayPlan();

        return [
            '<p><strong>Nothing for the financing.</strong> AtomPay is 0% markup and takes no advance, '
            .'so you repay exactly the cash price over '.$plan['months'].' months.</p>'
            .'<p>On a '.$this->rs($plan['price']).' product that is '.$this->rs($plan['monthly']).' a month, '
            .$this->rs($plan['total']).' in total. A '.$plan['fee_percent'].'% processing fee is shown with every plan '
            .'as a disclaimer, and delivery charges apply.</p>',
            [['label' => 'See a full plan', 'url' => route('home').'#plan']],
        ];
    }

    private function planFor(string $q): array
    {
        if (! preg_match('/([\d][\d,\s]{2,})/', $q, $m)) {
            return $this->cost();
        }

        $price = (int) preg_replace('/\D/', '', $m[1]);

        if ($price < 1000) {
            return $this->cost();
        }

        $plan = $this->quotes->atompayPlan($price);
        $last = $plan['last_monthly'] !== $plan['monthly']
            ? ', with '.$this->rs($plan['last_monthly']).' in the last month so the three add up exactly'
            : '';

        return [
            '<p>On a '.$this->rs($plan['price']).' product: <strong>'.$this->rs($plan['monthly']).' a month '
            .'for '.$plan['months'].' months</strong>'.$last.'.</p>'
            .'<p>No advance, no markup, '.$this->rs($plan['total']).' repaid in total. Your limit has to cover the '
            .'whole '.$this->rs($plan['price']).', because nothing is paid at checkout.</p>',
            [['label' => 'Products near this price', 'url' => route('products')]],
        ];
    }

    private function term(): array
    {
        return [
            '<p><strong>Always three months.</strong> There is nothing to choose &mdash; the term is fixed and the '
            .'markup is zero.</p>'
            .'<p>If you want longer, Atomshop seller financing runs 3 to 12 months, but it needs a minimum 20% advance '
            .'and charges a markup for each month. That is a different plan, arranged at Atomshop checkout.</p>',
            [['label' => 'How AtomPay works', 'url' => route('home').'#how']],
        ];
    }

    private function advance(): array
    {
        return [
            '<p><strong>None.</strong> AtomPay takes no advance and nothing is paid at checkout &mdash; your first '
            .'payment is a month later.</p>'
            .'<p>That is why your limit has to cover a product&rsquo;s whole price rather than part of it.</p>',
            [['label' => 'Check my limit', 'url' => auth()->check() ? route('account.applications') : route('register')]],
        ];
    }

    private function limit(): array
    {
        $income = (int) config('atompay.atompay_plan.example_income');
        $limitPct = (int) (config('atompay.credit.limit_ratio') * 100);
        $capPct = (int) (config('atompay.credit.instalment_ratio') * 100);

        return [
            '<p>Your purchase limit is <strong>'.$limitPct.'% of the monthly income you declare</strong>. '
            .'No single month may cost more than '.$capPct.'% of that income, or your disposable income after '
            .'expenses &mdash; whichever is lower.</p>'
            .'<p>So '.$this->rs($income).' a month earns about a '.$this->rs(Money::share($income, (float) config('atompay.credit.limit_ratio')))
            .' limit, with a '.$this->rs(Money::share($income, (float) config('atompay.credit.instalment_ratio'))).' monthly cap. '
            .'Staff set the final figure after checking your identity, your address and your income.</p>',
            [['label' => 'Apply for a limit', 'url' => auth()->check() ? route('account.application') : route('register')]],
        ];
    }

    private function apply(): array
    {
        return [
            '<p>Five checks, and you fill in two of them:</p>'
            .'<ul><li>Your details and CNIC &mdash; with a photo of the card and a selfie</li>'
            .'<li>An address check &mdash; a one-time visit by our team</li>'
            .'<li>Your income and expenses</li>'
            .'<li>A risk review, then the limit decision, both by AtomPay</li></ul>'
            .'<p>Have your CNIC, a selfie on the same phone, your monthly income figure and an address someone can '
            .'visit. You must be 18 or over.</p>',
            [['label' => 'Start my application', 'url' => auth()->check() ? route('account.application') : route('register')]],
        ];
    }

    private function eligibleProducts(): array
    {
        $count = $this->catalogue->count();

        if ($count === 0) {
            return [
                '<p>AtomPay is offered on selected listed products only &mdash; Atomshop marks them per product, and '
                .'some brands mark their whole catalogue. Look for the <strong>AtomPay eligible</strong> badge on a '
                .'listing.</p><p>Nothing is marked eligible at the moment.</p>',
                [['label' => 'Browse Atomshop.pk', 'url' => config('atompay.shop_url')]],
            ];
        }

        $cheapest = Product::query()->visible()->atompayEligible()->where('price', '>', 0)->min('price');

        return [
            '<p><strong>'.number_format($count).' '.Str::plural('product', $count).'</strong> on Atomshop.pk can be '
            .'bought with an AtomPay limit, from '.$this->rs((int) $cheapest).' upward. Look for the '
            .'<strong>AtomPay eligible</strong> badge on a listing.</p>'
            .'<p>It is never offered on custom orders or product offers.</p>',
            [['label' => 'See eligible products', 'url' => route('products')]],
        ];
    }

    private function payments(): array
    {
        return [
            '<p>Three equal monthly payments, the first a month after your order. The last one absorbs any rounding, '
            .'so the three add up to the price exactly.</p>'
            .'<p>A reminder goes out three days before each due date and on the day, by WhatsApp and email, and as a '
            .'push if you have the app. Your payments are recorded by the seller who delivered the order, or by '
            .'Atomshop.</p>',
            [['label' => 'My payments', 'url' => auth()->check() ? route('account.orders') : route('login')]],
        ];
    }

    private function apps(): array
    {
        $stores = config('atompay.api.store_url');
        $actions = array_values(array_filter([
            $stores['android'] ?? null ? ['label' => 'Google Play', 'url' => $stores['android']] : null,
            $stores['ios'] ?? null ? ['label' => 'App Store', 'url' => $stores['ios']] : null,
        ]));

        return [
            '<p>The AtomPay app is on <strong>Google Play and the App Store</strong>. It shows what is left of your '
            .'limit, every payment with its date, and pushes a reminder before one falls due.</p>'
            .'<p>Same account as this site and Atomshop.pk. AtomPay also works in your phone browser, so the app is a '
            .'convenience rather than a requirement.</p>',
            $actions,
        ];
    }

    private function account(): array
    {
        return [
            '<p><strong>One account for both sites.</strong> The same email or mobile number and password sign you in '
            .'here, in the AtomPay app, and at Atomshop.pk checkout &mdash; there is no second sign-up.</p>'
            .'<p>Signing up with an email sends a code by email; signing up with a mobile number sends it on WhatsApp. '
            .'A new password works on both sites straight away.</p>',
            [
                ['label' => 'Sign in', 'url' => route('login')],
                ['label' => 'Forgot password', 'url' => route('password.request')],
            ],
        ];
    }

    private function shopSide(): array
    {
        return [
            '<p>That one belongs to Atomshop rather than AtomPay. Atomshop works every AtomPay order itself, then a '
            .'verified seller in your area delivers the unit and records your payments &mdash; or, for a brand '
            .'partner&rsquo;s product, the brand hands it over.</p>'
            .'<p>Paying in installments changes nothing about what you receive: same item, same seller, same warranty. '
            .'Delivery is Lahore only.</p>',
            [['label' => 'Atomshop.pk', 'url' => config('atompay.shop_url')]],
        ];
    }

    private function support(): array
    {
        $support = config('atompay.api.support');
        $whatsapp = $support['whatsapp'] ?? $support['phone'] ?? null;

        $contact = $whatsapp
            ? '<p>Message us on WhatsApp at <strong>'.e($whatsapp).'</strong>.</p>'
            : '<p>Our FAQs cover most of it.</p>';

        $actions = array_values(array_filter([
            $whatsapp ? ['label' => 'WhatsApp us', 'url' => 'https://wa.me/'.preg_replace('/\D/', '', $whatsapp)] : null,
            ['label' => 'Read the FAQs', 'url' => route('faq')],
        ]));

        return ['<p>Happy to hand you over.</p>'.$contact, $actions];
    }

    private function whatIsIt(): array
    {
        return [
            '<p>AtomPay is Atomshop.pk&rsquo;s own installment plan: a verified spending limit you use at checkout on '
            .'eligible products, repaid over three months with <strong>no advance and 0% markup</strong>.</p>'
            .'<p>Atomshop is the shop &mdash; you browse, order and take delivery there. AtomPay is only the way you '
            .'pay for it, so nothing is actually bought on this site.</p>',
            [['label' => 'How it works', 'url' => route('home').'#how']],
        ];
    }

    private function fallback(): array
    {
        return [
            '<p>I am not sure about that one. I can help with what AtomPay costs, how to get a limit, which products '
            .'are eligible, and when your payments fall due.</p>'
            .'<p>Anything about delivery, warranty or a product&rsquo;s price belongs to Atomshop.pk.</p>',
            [
                ['label' => 'Read the FAQs', 'url' => route('faq')],
                ['label' => 'How it works', 'url' => route('home').'#how'],
            ],
        ];
    }

    /* ------------------------------------------------------------ plumbing */

    /** @param array{0: string, 1: array} $reply */
    private function shape(array $reply): array
    {
        return [
            'answer' => $reply[0],
            'actions' => $reply[1] ?? [],
            'chips' => $this->chips(),
        ];
    }

    private function rs(int $amount): string
    {
        return Money::format($amount, 'Rs. ');
    }
}
