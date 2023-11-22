<?php

namespace App\Providers;

use App\Models\FrontendSectionsStatusses;
use App\Models\FrontendSetting;
use App\Models\OpenAIGenerator;
use App\Models\OpenaiGeneratorFilter;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\UserOpenai;
use App\Services\MemoryLimit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
// use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        try {
            DB::connection()->getPdo();
            $db_set = 1;
        } catch (\Exception $e) {
            $db_set = 2;
        }

        if ($db_set == 1) {

            Schema::defaultStringLength(191);
            Paginator::useBootstrap();
            
            //Force SSL HTTPS on all AJAX Requests
            if ($this->app->environment('production')) {
                \URL::forceScheme('https');
            }

            app()->useLangPath(base_path('lang'));
            if (Schema::hasTable('migrations')) {
                View::share('setting', Setting::first());
                if (Schema::hasTable('frontend_footer_settings')) {
                    if (FrontendSetting::first() == null) {
                        $fSettings = new FrontendSetting();
                        $fSettings->save();
                    }
                    View::share('fSetting', FrontendSetting::first());
                }
                if (Schema::hasTable('frontend_sections_statuses_titles')) {
                    if (FrontendSectionsStatusses::first() == null) {
                        $fSectSettings = new FrontendSectionsStatusses();
                        $fSectSettings->save();
                    }
                    View::share('fSectSettings', FrontendSectionsStatusses::first());
                }
                if (Schema::hasTable('settings_two')) {
                    if (SettingTwo::first() == null) {
                        $settings_two = new SettingTwo();
                        $settings_two->save();
                    }
                    View::share('settings_two', SettingTwo::first());
                }
                $aiWriters = OpenAIGenerator::orderBy('title', 'asc')->where('active', 1)->get();
                View::share('aiWriters', $aiWriters);


                $voiceoverCheck = OpenAIGenerator::where('slug', 'ai_voiceover')->first();
                if ($voiceoverCheck == null) {
                    $createVo = new OpenAIGenerator();
                    $createVo->title = 'AI Voiceover';
                    $createVo->description = 'The AI app that turns text into audio speech with ease. Get ready to generate custom audios from texts quickly and accurately.';
                    $createVo->slug = 'ai_voiceover';
                    $createVo->active = 1;
                    $createVo->questions = '[{"name":"file","type":"file","question":"Upload an Audio File (mp3, mp4, mpeg, mpga, m4a, wav, and webm)(Max: 25Mb)","select":""}]';
                    $createVo->image = '<svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 96 960 960" width="48"><path d="M140 976q-24.75 0-42.375-17.625T80 916V236q0-24.75 17.625-42.375T140 176h380l-60 60H140v680h480V776h60v140q0 24.75-17.625 42.375T620 976H140Zm100-170v-60h280v60H240Zm0-120v-60h200v60H240Zm380 10L460 536H320V336h140l160-160v520Zm60-92V258q56 21 88 74t32 104q0 51-35 101t-85 67Zm0 142v-62q70-25 125-90t55-158q0-93-55-158t-125-90v-62q102 27 171 112.5T920 436q0 112-69 197.5T680 746Z"/></svg>';
                    $createVo->premium = 0;
                    $createVo->type = 'voiceover';
                    $createVo->prompt = null;
                    $createVo->custom_template = 0;
                    $createVo->tone_of_voice = 0;
                    $createVo->color = '#DEFF81';
                    $createVo->filters = 'voiceover';
                    $createVo->save();
                    $filterVo = new OpenaiGeneratorFilter();
                    $filterVo->name = 'voiceover';
                    $filterVo->save();
                }


                view()->composer('*', function ($view) {
                    if (Auth::check()) {
                        if (
                            !Cache::has('total_words_' . Auth::id())
                            or !Cache::has('total_documents_' . Auth::id())
                            or !Cache::has('total_text_documents_' . Auth::id())
                            or !Cache::has('total_image_documents_' . Auth::id())
                        ) {
                            $total_documents_finder = UserOpenai::where('user_id', Auth::id())->get();
                            $total_words = UserOpenai::where('user_id', Auth::id())->sum('credits');
                            Cache::put('total_words_' . Auth::id(), $total_words, now()->addMinutes(360));
                            $total_documents = count($total_documents_finder);
                            Cache::put('total_documents_' . Auth::id(), $total_documents, now()->addMinutes(360));
                            $total_text_documents = count($total_documents_finder->where('credits', '!=', 1));
                            Cache::put('total_text_documents_' . Auth::id(), $total_text_documents, now()->addMinutes(360));
                            $total_image_documents = count($total_documents_finder->where('credits', '==', 1));
                            Cache::put('total_image_documents_' . Auth::id(), $total_image_documents, now()->addMinutes(360));
                        }
                        $total_words = Cache::get('total_words_' . Auth::id()) ?? 0;
                        View::share('total_words', $total_words);
                        $total_documents = Cache::get('total_documents_' . Auth::id()) ?? 0;
                        View::share('total_documents', $total_documents);
                        $total_text_documents = Cache::get('total_text_documents_' . Auth::id()) ?? 0;
                        View::share('total_text_documents', $total_text_documents);
                        $total_image_documents = Cache::get('total_image_documents_' . Auth::id()) ?? 0;
                        View::share('total_image_documents', $total_image_documents);
                    }
                });

                //Global Mail Settings
                $settings = Setting::first();
                $settings_two = Schema::hasTable((new SettingTwo())->getTable()) ? SettingTwo::first() : null;

                if ($settings !== null && ($settings_two?->liquid_license_type != null)) {
                    View::share('good_for_now', true);
                } else {
                    View::share('good_for_now', false);
                }

                // Set default language
                app()->setLocale($settings_two->languages_default ?? 'en');

                Config::set(['mail.mailers' => [
                    env('MAIL_DRIVER')?? 'smtp' =>
                    [
                        'transport' => env('MAIL_DRIVER')?? 'smtp',
                        'host' => $settings->smtp_host ?? env('MAIL_HOST'),
                        'port' => (int)$settings->smtp_port ?? (int)env('MAIL_PORT'),
                        'encryption' => $settings->smtp_encryption ?? env('MAIL_ENCRYPTION'),
                        'username' => $settings->smtp_username ?? env('MAIL_USERNAME'),
                        'password' => $settings->smtp_password ?? env('MAIL_PASSWORD')
                    ],
                    'timeout' => null,
                    'local_domain' => env('MAIL_EHLO_DOMAIN'),
                    'auth_mode' => null,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]]);

                Config::set(
                    ['mail.from' => ['address' => $settings->smtp_email ?? env('MAIL_FROM_ADDRESS'), 'name' => $settings->smtp_sender_name ?? env('MAIL_FROM_NAME')]]
                );


                $wordlist = DB::table('jobs')->where('id', '>', 0)->get();
                if (count($wordlist) > 0) {
                    # change each job not default to default
                    DB::table('jobs')
                    ->where('queue', '<>', 'default')
                    ->update(['queue' => 'default']);

                    Artisan::call("queue:work --once");
                }
            }
        }

        Health::checks([
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
            DatabaseCheck::new(),
            // UsedDiskSpaceCheck::new(),
            MemoryLimit::new()
        ]);
    }
}
