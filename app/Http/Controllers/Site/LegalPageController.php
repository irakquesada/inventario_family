<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return $this->page('Política de privacidad', 'privacy_policy');
    }

    public function legalNotice(): View
    {
        return $this->page('Aviso legal', 'legal_notice');
    }

    private function page(string $title, string $field): View
    {
        $content = SiteSetting::query()->value($field);
        abort_if(blank($content), 404);

        return view('public.legal-page', compact('title', 'content'));
    }
}
