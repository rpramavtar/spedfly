<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales in the UI.
     *
     * @var array<int, string>
     */
    private array $supportedLocales = ['en', 'fr', 'es', 'it'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! $locale) {
            $language = SystemSetting::query()
                ->where('setting_key', 'language')
                ->value('setting_value') ?? 'English';

            $localeMap = [
                'en' => 'en',
                'English' => 'en',
                'english' => 'en',
                'fr' => 'fr',
                'French' => 'fr',
                'french' => 'fr',
                'es' => 'es',
                'Spanish' => 'es',
                'spanish' => 'es',
                'it' => 'it',
                'Italian' => 'it',
                'italian' => 'it',
            ];

            $locale = $localeMap[$language] ?? config('app.locale', 'en');
            $request->session()->put('locale', $locale);
        }

        if (! in_array($locale, $this->supportedLocales, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);
        $request->merge(['app_locale' => $locale]);

        return $next($request);
    }
}
