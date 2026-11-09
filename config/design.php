<?php

/*
|--------------------------------------------------------------------------
| Site design: v1 or v2
|--------------------------------------------------------------------------
| AtomPay carries two front-ends at once, the same way AtomShop does
| (see atomshop/.claude/WEBSITE-V2-PLAN.md):
|
|   v1  the original Tailwind + Vite design (resources/views/*, resources/css)
|   v2  the Atomshop.pk storefront v2 design system, so AtomPay and the shop
|       look like one company (resources/views/v2/*, public/web/v2/*)
|
| Nothing was moved to build v2. `atompay_view('home.index')` renders
| `v2.home.index` when v2 is on AND that view exists, and otherwise falls back
| to the v1 view at its original path - so pages migrate one at a time and an
| unmigrated page keeps working untouched.
|
| URLs never change. There is no /v2/ anywhere.
*/

return [

    /*
    | What visitors get. Flipped to v2 on 9 Nov 2026, once every page a visitor
    | can reach had a v2 view.
    |
    | One page does not: /my/application, the four-step KYC wizard, which still
    | renders its v1 view inside v2 chrome (docs/design-v2-migration.md). It
    | works - the fallback is what makes that safe - but it is the one visible
    | seam in a v2 session, so finish it before this is anything but obvious.
    |
    | The staff review screens stay v1 deliberately: internal, never customer
    | facing, exactly as AtomShop keeps its own admin out of its switch.
    */
    'default' => env('ATOMPAY_DESIGN', 'v2'),

    'allowed' => ['v1', 'v2'],

    /*
    | Staff preview: ?design=v2 sticks in the session so the whole site can be
    | walked in the other design. Allowed for staff anywhere, and for anyone on
    | a non-production environment. A previewed response is sent
    | X-Robots-Tag: noindex, so a crawler never indexes the design that is not
    | live. See App\Http\Middleware\SetDesign.
    */
    'preview' => [
        'enabled' => env('ATOMPAY_DESIGN_PREVIEW', true),
        'session_key' => 'atompay.design',
        'query_key' => 'design',
    ],

    /*
    | v2 assets are static files under public/web/v2 - no Vite, no npm on the
    | server, exactly like AtomShop's. site.css is a verbatim copy of
    | atomshop/public/web/v2/css/site.css so the two can be re-synced with a
    | file copy; everything AtomPay adds lives in atompay.css, loaded after it.
    */
    'assets' => [
        'css' => ['web/v2/css/site.css', 'web/v2/css/atompay.css'],
        'js' => ['web/v2/js/site.js'],
        // Plus Jakarta Sans, the v2 type family. Google Fonts is reached from
        // the page, so config/security.php allows those two hosts.
        'font_css' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
    ],

];
