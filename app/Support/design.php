<?php

/*
|--------------------------------------------------------------------------
| Design helpers
|--------------------------------------------------------------------------
| Autoloaded through composer.json "autoload.files". Controllers call
| atompay_view() instead of view(), and every v2 view and layout reads
| design_asset() so the switch has exactly one source of truth.
*/

use Illuminate\Contracts\View\View as ViewContract;

if (! function_exists('design')) {
    /** The design serving this request: 'v1' or 'v2'. Set by SetDesign. */
    function design(): string
    {
        return config('design.active') ?? config('design.default', 'v1');
    }
}

if (! function_exists('design_is_v2')) {
    function design_is_v2(): bool
    {
        return design() === 'v2';
    }
}

if (! function_exists('design_view')) {
    /**
     * The view NAME this design should use: the v2 one when v2 is on and it
     * exists, otherwise the v1 one at its original path. This is why nothing
     * had to be moved to add v2, and why a page with no v2 view still works.
     *
     * Use it where a name is needed rather than a rendered view - @extends and
     * @include in Blade, and Mailable::view(), which is resolved with no
     * request in front of it and so follows the site's live design rather than
     * anybody's ?design= preview.
     *
     *   design_view('emails.layout') -> 'v2.emails.layout' or 'emails.layout'
     */
    function design_view(string $view): string
    {
        $v2 = 'v2.'.$view;

        return design_is_v2() && view()->exists($v2) ? $v2 : $view;
    }
}

if (! function_exists('atompay_view')) {
    /**
     * Render the view this design should use. Controllers call this instead of
     * view().
     *
     * atompay_view('home.index') -> 'v2.home.index' or 'home.index'
     */
    function atompay_view(string $view, array $data = [], array $mergeData = []): ViewContract
    {
        return view(design_view($view), $data, $mergeData);
    }
}

if (! function_exists('design_asset')) {
    /**
     * A v2 asset URL with a cache-busting stamp taken from the file itself,
     * so a deploy that only copies files still busts the browser cache.
     */
    function design_asset(string $path): string
    {
        $url = asset($path);
        $stamp = @filemtime(public_path($path));

        return $stamp ? $url.'?v='.$stamp : $url;
    }
}
