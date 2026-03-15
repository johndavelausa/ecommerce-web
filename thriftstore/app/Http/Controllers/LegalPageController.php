<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        $content = (string) SystemSetting::get('page_privacy_policy', '');
        return view('legal.page', ['title' => 'Privacy Policy', 'content' => $content]);
    }

    public function terms(): View
    {
        $content = (string) SystemSetting::get('page_terms_of_service', '');
        return view('legal.page', ['title' => 'Terms of Service', 'content' => $content]);
    }

    public function cookieSettings(): View
    {
        $content = (string) SystemSetting::get('page_cookie_settings', '');
        return view('legal.page', ['title' => 'Cookie Settings', 'content' => $content]);
    }
}
