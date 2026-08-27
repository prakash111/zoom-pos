<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PlatformBranding;

class PublicPageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->is_active, 404);

        return view('public.page', [
            'page' => $page,
            'branding' => PlatformBranding::current(),
            'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(),
        ]);
    }
}
