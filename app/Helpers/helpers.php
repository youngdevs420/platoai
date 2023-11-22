<?php

use App\Http\Controllers\PaymentController;

use App\Models\Activity;
use App\Models\Gateways;
use App\Models\Setting;
use App\Models\Subscriptions;
use App\Models\YokassaSubscriptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Post;
use App\Models\UserUpvote;
use App\Models\User;
use App\Models\SettingTwo;
use App\Models\PrivacyTerms;
use App\Models\UserCategory;
use Illuminate\Support\Facades\Log;


function activeRoute($route_name){
    if (Route::currentRouteName() == $route_name){
        return 'active';
    }
}

function activeRouteBulk($route_names){
    $current_route = Route::currentRouteName();
    if (in_array($current_route, $route_names)){
        return 'active';
    }
}

function activeRouteBulkShow($route_names){
    $current_route = Route::currentRouteName();
    if (in_array($current_route, $route_names)){
        return 'show';
    }
}


function createActivity($user_id, $activity_type, $activity_title, $url){
    $activityEntry = new Activity();
    $activityEntry->user_id = $user_id;
    $activityEntry->activity_type = $activity_type;
    $activityEntry->activity_title = $activity_title;
    $activityEntry->url = $url;
    $activityEntry->save();

}

function percentageChange($old, $new, int $precision = 1){
    if ($old == 0) {
        $old++;
        $new++;
    }
    $change = round((($new - $old) / $old) * 100, $precision);

    if ($change < 0 ){
        return '<span class="inline-flex items-center leading-none !ms-2 text-[var(--tblr-red)] text-[10px] bg-[rgba(var(--tblr-red-rgb),0.15)] px-[5px] py-[3px] rounded-[3px]">
            <svg class="mr-1 -scale-100" width="7" height="4" viewBox="0 0 7 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 3.2768C0 3.32591 0.0245541 3.38116 0.061384 3.41799L0.368304 3.72491C0.405134 3.76174 0.46038 3.78629 0.509487 3.78629C0.558594 3.78629 0.61384 3.76174 0.65067 3.72491L3.06306 1.31252L5.47545 3.72491C5.51228 3.76174 5.56752 3.78629 5.61663 3.78629C5.67188 3.78629 5.72098 3.76174 5.75781 3.72491L6.06473 3.41799C6.10156 3.38116 6.12612 3.32591 6.12612 3.2768C6.12612 3.2277 6.10156 3.17245 6.06473 3.13562L3.20424 0.275129C3.16741 0.238299 3.11217 0.213745 3.06306 0.213745C3.01395 0.213745 2.95871 0.238299 2.92188 0.275129L0.061384 3.13562C0.0245541 3.17245 0 3.2277 0 3.2768Z"/>
            </svg>
            '.$change.'%
        </span>';
    }else{
        return '<span class="inline-flex items-center leading-none !ms-2 text-[var(--tblr-green)] text-[10px] bg-[rgba(var(--tblr-green-rgb),0.15)] px-[5px] py-[3px] rounded-[3px]">
                    <svg class="mr-1" width="7" height="4" viewBox="0 0 7 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0 3.2768C0 3.32591 0.0245541 3.38116 0.061384 3.41799L0.368304 3.72491C0.405134 3.76174 0.46038 3.78629 0.509487 3.78629C0.558594 3.78629 0.61384 3.76174 0.65067 3.72491L3.06306 1.31252L5.47545 3.72491C5.51228 3.76174 5.56752 3.78629 5.61663 3.78629C5.67188 3.78629 5.72098 3.76174 5.75781 3.72491L6.06473 3.41799C6.10156 3.38116 6.12612 3.32591 6.12612 3.2768C6.12612 3.2277 6.10156 3.17245 6.06473 3.13562L3.20424 0.275129C3.16741 0.238299 3.11217 0.213745 3.06306 0.213745C3.01395 0.213745 2.95871 0.238299 2.92188 0.275129L0.061384 3.13562C0.0245541 3.17245 0 3.2277 0 3.2768Z"/>
                    </svg>
                    '.$change.'%
                </span>';
    }


}

function percentageChangeSign($old, $new, int $precision = 2){

    if (percentageChange($old, $new) > 0){
        return 'plus';
    }else{
        return 'minus';
    }

}


function currency(){
    $setting = \App\Models\Setting::first();
    $curr = \App\Models\Currency::where('id', $setting->default_currency)->first();
    if(in_array($curr->code, config('currency.needs_code_with_symbol'))){
        $curr->symbol = $curr->code . " " . $curr->symbol;
    }
    return $curr;
}

function getSubscription(){
    $userId=Auth::user()->id;
    $activeSub = Subscriptions::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();
    if($activeSub == null) {
        $activeSub = YokassaSubscriptions::where([['subscription_status', '=', 'active'], ['user_id', '=', $userId]])->first();
    }
    return $activeSub;
}

function getSubscriptionActive(){
    return getSubscription();
}

function getSubscriptionStatus(){
    return PaymentController::getSubscriptionStatus();
}

function checkIfTrial(){
    return PaymentController::checkIfTrial();
}

function getSubscriptionName(){
    $user = Auth::user();
    return \App\Models\PaymentPlans::where('id', getSubscription()->name)->first()->name;
}

function getYokassaSubscriptionName(){
    $user = Auth::user();
    return \App\Models\PaymentPlans::where('id', getYokassaSubscription()->plan_id)->first()->plan_id;
}

function getSubscriptionRenewDate()
{
    return PaymentController::getSubscriptionRenewDate();
}

function getSubscriptionDaysLeft()
{
    return PaymentController::getSubscriptionDaysLeft();
}

//Templates favorited
function isFavorited($template_id){
    $isFav = \App\Models\UserFavorite::where('user_id', Auth::id())->where('openai_id', $template_id)->exists();
    return $isFav;
}

//Country Flags
function country2flag(string $countryCode): string
{

    if (strpos($countryCode, '-') !== false) {
        $countryCode = substr($countryCode, strpos($countryCode, '-') + 1);
    } elseif (strpos($countryCode, '_') !== false) {
        $countryCode = substr($countryCode, strpos($countryCode, '_') + 1);
    }

    if ( $countryCode === 'el' ){
        $countryCode = 'gr';
    }elseif ( $countryCode === 'da' ){
        $countryCode = 'dk';
    }
    
    return (string) preg_replace_callback(
        '/./',
        static fn (array $letter) => mb_chr(ord($letter[0]) % 32 + 0x1F1E5),
        $countryCode
    );
}

//Memory Limit
function getServerMemoryLimit() {
    return (int) ini_get('memory_limit');
}

//Count Words
function countWords($text){

    $encoding = mb_detect_encoding($text);

    if ($encoding === 'UTF-8') {
        // Count Chinese words by splitting the string into individual characters
        $words = preg_match_all('/\p{Han}|\p{L}+|\p{N}+/u', $text);
    } else {
        // For other languages, use str_word_count()
        $words = str_word_count($text, 0, $encoding);
    }

    return (int)$words;

}

function getDefinedLangs() {
    $fields = \DB::connection('locations')->getSchemaBuilder()->getColumnListing('strings');
    $exceptions = ['en','code','created_at','updated_at'];
    $filtered = collect($fields)->filter(function ($value, $key) use($exceptions){
        if (!in_array($value,$exceptions) ) {
            return $value;
        }
    });
    return $filtered->all();
}

function getVoiceNames($hash) {
    $voiceNames =[
        "af-ZA-Standard-A" => "Ayanda (". __('Female').")",
        "ar-XA-Standard-A" => "Fatima (". __('Female').")",
        "ar-XA-Standard-B" => "Ahmed (". __('Male').")",
        "ar-XA-Standard-C" => "Mohammed (". __('Male').")",
        "ar-XA-Standard-D" => "Aisha (". __('Female').")",
        "ar-XA-Wavenet-A" => "Layla (". __('Female').")",
        "ar-XA-Wavenet-B" => "Ali (". __('Male').")",
        "ar-XA-Wavenet-C" => "Omar (". __('Male').")",
        "ar-XA-Wavenet-D" => "Zahra (". __('Female').")",
        "eu-ES-Standard-A" => "Ane (". __('Female').")",
        "bn-IN-Standard-A" => "Ananya (". __('Female').")",
        "bn-IN-Standard-B" => "Aryan (". __('Male').")",
        "bn-IN-Wavenet-A" => "Ishita (". __('Female').")",
        "bn-IN-Wavenet-B" => "Arry (". __('Male').")",
        "bg-BG-Standard-A" => "Elena (". __('Female').")",
        "ca-ES-Standard-A" => "Laia (". __('Female').")",
        "yue-HK-Standard-A" => "Wing (". __('Female').")",
        "yue-HK-Standard-B" => "Ho (". __('Male').")",
        "yue-HK-Standard-C" => "Siu (". __('Female').")",
        "yue-HK-Standard-D" => "Lau (". __('Male').")",
        "cs-CZ-Standard-A" => "Tereza (". __('Female').")",
        "cs-CZ-Wavenet-A" => "Karolína (". __('Female').")",
        //"da-DK-Neural2-D" => "Neural2 - FEMALE",
        //"da-DK-Neural2-F" => "Neural2 - MALE",                    
        "da-DK-Standard-A" => "Emma (". __('Female').")",
        "da-DK-Standard-A" => "Freja (". __('Female').")",
        "da-DK-Standard-A" => "Ida (". __('Female').")",
        "da-DK-Standard-C" => "Noah (". __('Male').")",
        "da-DK-Standard-D" => "Mathilde (". __('Female').")",
        "da-DK-Standard-E" => "Clara (". __('Female').")",
        "da-DK-Wavenet-A" => "Isabella (". __('Female').")",
        "da-DK-Wavenet-C" => "Lucas (". __('Male').")",
        "da-DK-Wavenet-D" => "Olivia (". __('Female').")",
        "da-DK-Wavenet-E" => "Emily (". __('Female').")",
        "nl-BE-Standard-A" => "Emma (". __('Female').")",
        "nl-BE-Standard-B" => "Thomas (". __('Male').")",
        "nl-BE-Wavenet-A" => "Sophie (". __('Female').")",
        "nl-BE-Wavenet-B" => "Lucas (". __('Male').")",
        "nl-NL-Standard-A" => "Emma (". __('Female').")",
        "nl-NL-Standard-B" => "Daan (". __('Male').")",
        "nl-NL-Standard-C" => "Luuk (". __('Male').")",
        "nl-NL-Standard-D" => "Lotte (". __('Female').")",
        "nl-NL-Standard-E" => "Sophie (". __('Female').")",
        "nl-NL-Wavenet-A" => "Mila (". __('Female').")",
        "nl-NL-Wavenet-B" => "Sem (". __('Male').")",
        "nl-NL-Wavenet-C" => "Stijn (". __('Male').")",
        "nl-NL-Wavenet-D" => "Fenna (". __('Female').")",
        "nl-NL-Wavenet-E" => "Eva (". __('Female').")",
        //"en-AU-Neural2-A" => "Neural2 - FEMALE",
        //"en-AU-Neural2-B" => "Neural2 - MALE",
        //"en-AU-Neural2-C" => "Neural2 - FEMALE",
        //"en-AU-Neural2-D" => "Neural2 - MALE",
        "en-AU-News-E" => "Emma (". __('Female').")",
        "en-AU-News-F" => "Olivia (". __('Female').")",
        "en-AU-News-G" => "Liam (". __('Male').")",
        "en-AU-Polyglot-1" => "Noah (". __('Male').")",
        "en-AU-Standard-A" => "Charlotte (". __('Female').")",
        "en-AU-Standard-B" => "Oliver (". __('Male').")",
        "en-AU-Standard-C" => "Ava (". __('Female').")",
        "en-AU-Standard-D" => "Jack (". __('Male').")",
        "en-AU-Wavenet-A" => "Sophie (". __('Female').")",
        "en-AU-Wavenet-B" => "William (". __('Male').")",
        "en-AU-Wavenet-C" => "Amelia (". __('Female').")",
        "en-AU-Wavenet-D" => "Thomas (". __('Male').")",
        "en-IN-Standard-A" => "Aditi (". __('Female').")",
        "en-IN-Standard-B" => "Arjun (". __('Male').")",
        "en-IN-Standard-C" => "Rohan (". __('Male').")",
        "en-IN-Standard-D" => "Ananya (". __('Female').")",
        "en-IN-Wavenet-A" => "Alisha (". __('Female').")",
        "en-IN-Wavenet-B" => "Aryan (". __('Male').")",
        "en-IN-Wavenet-C" => "Kabir (". __('Male').")",
        "en-IN-Wavenet-D" => "Diya (". __('Female').")",
        //"en-GB-Neural2-A" => "Neural2 - FEMALE",
        //"en-GB-Neural2-B" => "Neural2 - MALE",
        //"en-GB-Neural2-C" => "Neural2 - FEMALE",
        //"en-GB-Neural2-D" => "Neural2 - MALE",
        //"en-GB-Neural2-F" => "Neural2 - FEMALE",
        "en-GB-News-G" => "Amelia (". __('Female').")",
        "en-GB-News-H" => "Elise (". __('Female').")",
        "en-GB-News-I" => "Isabella (". __('Female').")",
        "en-GB-News-J" => "Jessica (". __('Female').")",
        "en-GB-News-K" => "Alexander (". __('Male').")",
        "en-GB-News-L" => "Benjamin (". __('Male').")",
        "en-GB-News-M" => "Charles (". __('Male').")",
        "en-GB-Standard-A" => "Emily (". __('Female').")",
        "en-GB-Standard-B" => "John (". __('Male').")",
        "en-GB-Standard-C" => "Mary (". __('Female').")",
        "en-GB-Standard-D" => "Peter (". __('Male').")",
        "en-GB-Standard-F" => "Sarah (". __('Female').")",
        "en-GB-Wavenet-A" => "Ava (". __('Female').")",
        "en-GB-Wavenet-B" => "David (". __('Male').")",
        "en-GB-Wavenet-C" => "Emily (". __('Female').")",
        "en-GB-Wavenet-D" => "James (". __('Male').")",
        "en-GB-Wavenet-F" => "Sophie (". __('Female').")",
        //"en-US-Neural2-A" => "Neural2 - MALE",
        //"en-US-Neural2-C" => "Neural2 - FEMALE",
        //"en-US-Neural2-D" => "Neural2 - MALE",
        //"en-US-Neural2-E" => "Neural2 - FEMALE",
        //"en-US-Neural2-F" => "Neural2 - FEMALE",
        //"en-US-Neural2-G" => "Neural2 - FEMALE",
        //"en-US-Neural2-H" => "Neural2 - FEMALE",
        //"en-US-Neural2-I" => "Neural2 - MALE",
        //"en-US-Neural2-J" => "Neural2 - MALE",
        "en-US-News-K" => "Lily (". __('Female').")",
        "en-US-News-L" => "Olivia (". __('Female').")",
        "en-US-News-M" => "Noah (". __('Male').")",
        "en-US-News-N" => "Oliver (". __('Male').")",
        "en-US-Polyglot-1" => "John (". __('Male').")",
        "en-US-Standard-A" => "Michael (". __('Male').")",
        "en-US-Standard-B" => "David (". __('Male').")",
        "en-US-Standard-C" => "Emma (". __('Female').")",
        "en-US-Standard-D" => "William (". __('Male').")",
        "en-US-Standard-E" => "Ava (". __('Female').")",
        "en-US-Standard-F" => "Sophia (". __('Female').")",
        "en-US-Standard-G" => "Isabella (". __('Female').")",
        "en-US-Standard-H" => "Charlotte (". __('Female').")",
        "en-US-Standard-I" => "James (". __('Male').")",
        "en-US-Standard-J" => "Lucas (". __('Male').")",
        "en-US-Studio-M" => "Benjamin (". __('Male').")",
        "en-US-Studio-O" => "Eleanor (". __('Female').")",
        "en-US-Wavenet-A" => "Alexander (". __('Male').")",
        "en-US-Wavenet-B" => "Benjamin (". __('Male').")",
        "en-US-Wavenet-C" => "Emily (". __('Female').")",
        "en-US-Wavenet-D" => "James (". __('Male').")",
        "en-US-Wavenet-E" => "Ava (". __('Female').")",
        "en-US-Wavenet-F" => "Sophia (". __('Female').")",
        "en-US-Wavenet-G" => "Isabella (". __('Female').")",
        "en-US-Wavenet-H" => "Charlotte (". __('Female').")",
        "en-US-Wavenet-I" => "Alexander (". __('Male').")",
        "en-US-Wavenet-J" => "Lucas (". __('Male').")",
        "fil-PH-Standard-A" => "Maria (". __('Female').")",
        "fil-PH-Standard-B" => "Juana (". __('Female').")",
        "fil-PH-Standard-C" => "Juan (". __('Male').")",
        "fil-PH-Standard-D" => "Pedro (". __('Male').")",
        "fil-PH-Wavenet-A" => "Maria (". __('Female').")",
        "fil-PH-Wavenet-B" => "Juana (". __('Female').")",
        "fil-PH-Wavenet-C" => "Juan (". __('Male').")",
        "fil-PH-Wavenet-D" => "Pedro (". __('Male').")",
        //"fil-ph-Neural2-A" => "Neural2 - FEMALE",
        //"fil-ph-Neural2-D" => "Neural2 - MALE",
        "fi-FI-Standard-A" => "Sofia (". __('Female').")",
        "fi-FI-Wavenet-A" => "Sofianna (". __('Female').")",
        //"fr-CA-Neural2-A" => "Neural2 - FEMALE",
        //"fr-CA-Neural2-B" => "Neural2 - MALE",
        //"fr-CA-Neural2-C" => "Neural2 - FEMALE",
        //"fr-CA-Neural2-D" => "Neural2 - MALE",
        "fr-CA-Standard-A" => "Emma (". __('Female').")",
        "fr-CA-Standard-B" => "Jean (". __('Male').")",
        "fr-CA-Standard-C" => "Gabrielle (". __('Female').")",
        "fr-CA-Standard-D" => "Thomas (". __('Male').")",
        "fr-CA-Wavenet-A" => "Amelie (". __('Female').")",
        "fr-CA-Wavenet-B" => "Antoine (". __('Male').")",
        "fr-CA-Wavenet-C" => "Gabrielle (". __('Female').")",
        "fr-CA-Wavenet-D" => "Thomas (". __('Male').")",
        //"fr-FR-Neural2-A" => "Neural2 - FEMALE",
        //"fr-FR-Neural2-B" => "Neural2 - MALE",
        //"fr-FR-Neural2-C" => "Neural2 - FEMALE",
        //"fr-FR-Neural2-D" => "Neural2 - MALE",
        //"fr-FR-Neural2-E" => "Neural2 - FEMALE",
        "fr-FR-Polyglot-1" => "Jean (". __('Male').")",
        "fr-FR-Standard-A" => "Marie (". __('Female').")",
        "fr-FR-Standard-B" => "Pierre (". __('Male').")",
        "fr-FR-Standard-C" => "Sophie (". __('Female').")",
        "fr-FR-Standard-D" => "Paul (". __('Male').")",
        "fr-FR-Standard-E" => "Julie (". __('Female').")",
        "fr-FR-Wavenet-A" => "Elise (". __('Female').")",
        "fr-FR-Wavenet-B" => "Nicolas (". __('Male').")",
        "fr-FR-Wavenet-C" => "Clara (". __('Female').")",
        "fr-FR-Wavenet-D" => "Antoine (". __('Male').")",
        "fr-FR-Wavenet-E" => "Amelie (". __('Female').")",
        "gl-ES-Standard-A" => "Ana (". __('Female').")",
        //"de-DE-Neural2-B" => "Neural2 - MALE",
        //"de-DE-Neural2-C" => "Neural2 - FEMALE",
        //"de-DE-Neural2-D" => "Neural2 - MALE",
        //"de-DE-Neural2-F" => "Neural2 - FEMALE",
        "de-DE-Polyglot-1" => "Johannes (". __('Male').")",
        "de-DE-Standard-A" => "Anna (". __('Female').")",
        "de-DE-Standard-B" => "Max (". __('Male').")",
        "de-DE-Standard-C" => "Sophia (". __('Female').")",
        "de-DE-Standard-D" => "Paul (". __('Male').")",
        "de-DE-Standard-E" => "Erik (". __('Male').")",
        "de-DE-Standard-F" => "Lina (". __('Female').")",
        "de-DE-Wavenet-A" => "Eva (". __('Female').")",
        "de-DE-Wavenet-B" => "Felix (". __('Male').")",
        "de-DE-Wavenet-C" => "Emma (". __('Female').")",
        "de-DE-Wavenet-D" => "Lukas (". __('Male').")",
        "de-DE-Wavenet-E" => "Nico (". __('Male').")",
        "de-DE-Wavenet-F" => "Mia (". __('Female').")",
        "el-GR-Standard-A" => "Ελένη (". __('Female').")",
        "el-GR-Wavenet-A" => "Ελένη (". __('Female').")",
        "gu-IN-Standard-A" => "દિવ્યા (". __('Female').")",
        "gu-IN-Standard-B" => "કિશોર (". __('Male').")",
        "gu-IN-Wavenet-A" => "દિવ્યા (". __('Female').")",
        "gu-IN-Wavenet-B" => "કિશોર (". __('Male').")",
        "he-IL-Standard-A" => "Tamar (". __('Female').")",
        "he-IL-Standard-B" => "David (". __('Male').")",
        "he-IL-Standard-C" => "Michal (". __('Female').")",
        "he-IL-Standard-D" => "Jonathan (". __('Male').")",
        "he-IL-Wavenet-A" => "Yael (". __('Female').")",
        "he-IL-Wavenet-B" => "Eli (". __('Male').")",
        "he-IL-Wavenet-C" => "Abigail (". __('Female').")",
        "he-IL-Wavenet-D" => "Alex (". __('Male').")",
        //"hi-IN-Neural2-A" => "Neural2 - FEMALE",
        //"hi-IN-Neural2-B" => "Neural2 - MALE",
        //"hi-IN-Neural2-C" => "Neural2 - MALE",
        //"hi-IN-Neural2-D" => "Neural2 - FEMALE",
        "hi-IN-Standard-A" => "Aditi (". __('Female').")",
        "hi-IN-Standard-B" => "Abhishek (". __('Male').")",
        "hi-IN-Standard-C" => "Aditya (". __('Male').")",
        "hi-IN-Standard-D" => "Anjali (". __('Female').")",
        "hi-IN-Wavenet-A" => "Kiara (". __('Female').")",
        "hi-IN-Wavenet-B" => "Rohan (". __('Male').")",
        "hi-IN-Wavenet-C" => "Rishabh (". __('Male').")",
        "hi-IN-Wavenet-D" => "Srishti (". __('Female').")",
        "hu-HU-Standard-A" => "Eszter (". __('Female').")",
        "hu-HU-Wavenet-A" => "Lilla (". __('Female').")",
        "is-IS-Standard-A" => "Guðrún (". __('Female').")",
        "id-ID-Standard-A" => "Amelia (". __('Female').")",
        "id-ID-Standard-B" => "Fajar (". __('Male').")",
        "id-ID-Standard-C" => "Galih (". __('Male').")",
        "id-ID-Standard-D" => "Kiara (". __('Female').")",
        "id-ID-Wavenet-A" => "Nadia (". __('Female').")",
        "id-ID-Wavenet-B" => "Reza (". __('Male').")",
        "id-ID-Wavenet-C" => "Satria (". __('Male').")",
        "id-ID-Wavenet-D" => "Vania (". __('Female').")",
        //"it-IT-Neural2-A" => "Neural2 - FEMALE",
        //"it-IT-Neural2-C" => "Neural2 - MALE",
        "it-IT-Standard-A" => "Chiara (". __('Female').")",
        "it-IT-Standard-B" => "Elisa (". __('Female').")",
        "it-IT-Standard-C" => "Matteo (". __('Male').")",
        "it-IT-Standard-D" => "Riccardo (". __('Male').")",
        "it-IT-Wavenet-A" => "Valentina (". __('Female').")",
        "it-IT-Wavenet-B" => "Vittoria (". __('Female').")",
        "it-IT-Wavenet-C" => "Andrea (". __('Male').")",
        "it-IT-Wavenet-D" => "Luca (". __('Male').")",
        //"ja-JP-Neural2-B" => "Neural2 - FEMALE",
        //"ja-JP-Neural2-C" => "Neural2 - MALE",
        //"ja-JP-Neural2-D" => "Neural2 - MALE",
        "ja-JP-Standard-A" => "Akane (". __('Female').")",
        "ja-JP-Standard-B" => "Emi (". __('Female').")",
        "ja-JP-Standard-C" => "Daisuke (". __('Male').")",
        "ja-JP-Standard-D" => "Kento (". __('Male').")",
        "ja-JP-Wavenet-A" => "Haruka (". __('Female').")",
        "ja-JP-Wavenet-B" => "Rin (". __('Female').")",
        "ja-JP-Wavenet-C" => "Shun (". __('Male').")",
        "ja-JP-Wavenet-D" => "Yuta (". __('Male').")",
        "kn-IN-Standard-A" => "Dhanya (". __('Female').")",
        "kn-IN-Standard-B" => "Keerthi (". __('Male').")",
        "kn-IN-Wavenet-A" => "Meena (". __('Female').")",
        "kn-IN-Wavenet-B" => "Nandini (". __('Male').")",
        //"ko-KR-Neural2-A" => "Neural2 - FEMALE",
        //"ko-KR-Neural2-B" => "Neural2 - FEMALE",
        //"ko-KR-Neural2-C" => "Neural2 - MALE",
        "ko-KR-Standard-A" => "So-young (". __('Female').")",
        "ko-KR-Standard-B" => "Se-yeon (". __('Female').")",
        "ko-KR-Standard-C" => "Min-soo (". __('Male').")",
        "ko-KR-Standard-D" => "Seung-woo (". __('Male').")",
        "ko-KR-Wavenet-A" => "Ji-soo (". __('Female').")",
        "ko-KR-Wavenet-B" => "Yoon-a (". __('Female').")",
        "ko-KR-Wavenet-C" => "Tae-hyun (". __('Male').")",
        "ko-KR-Wavenet-D" => "Jun-ho (". __('Male').")",
        "lv-LV-Standard-A" => "Raivis (". __('Male').")",
        "lv-LT-Standard-A" => "Raivis (". __('Male').")",
        "ms-MY-Standard-A" => "Amira (". __('Female').")",
        "ms-MY-Standard-B" => "Danial (". __('Male').")",
        "ms-MY-Standard-C" => "Eira (". __('Female').")",
        "ms-MY-Standard-D" => "Farhan (". __('Male').")",
        "ms-MY-Wavenet-A" => "Hana (". __('Female').")",
        "ms-MY-Wavenet-B" => "Irfan (". __('Male').")",
        "ms-MY-Wavenet-C" => "Janna (". __('Female').")",
        "ms-MY-Wavenet-D" => "Khairul (". __('Male').")",
        "ml-IN-Standard-A" => "Aishwarya (". __('Female').")",
        "ml-IN-Standard-B" => "Dhruv (". __('Male').")",
        "ml-IN-Wavenet-A" => "Deepthi (". __('Female').")",
        "ml-IN-Wavenet-B" => "Gautam (". __('Male').")",
        "ml-IN-Wavenet-C" => "Isha (". __('Female').")",
        "ml-IN-Wavenet-D" => "Kabir (". __('Male').")",
        "cmn-CN-Standard-A" => "Xiaomei (". __('Female').")",
        "cmn-CN-Standard-B" => "Lijun (". __('Male').")",
        "cmn-CN-Standard-C" => "Minghao (". __('Male').")",
        "cmn-CN-Standard-D" => "Yingying (". __('Female').")",
        "cmn-CN-Wavenet-A" => "Shanshan (". __('Female').")",
        "cmn-CN-Wavenet-B" => "Chenchen (". __('Male').")",
        "cmn-CN-Wavenet-C" => "Jiahao (". __('Male').")",
        "cmn-CN-Wavenet-D" => "Yueyu (". __('Female').")",
        "cmn-TW-Standard-A" => "Jingwen (". __('Female').")",
        "cmn-TW-Standard-B" => "Jinghao (". __('Male').")",
        "cmn-TW-Standard-C" => "Tingting (". __('Female').")",
        "cmn-TW-Wavenet-A" => "Yunyun (". __('Female').")",
        "cmn-TW-Wavenet-B" => "Zhenghao (". __('Male').")",
        "cmn-TW-Wavenet-C" => "Yuehan (". __('Female').")",
        "mr-IN-Standard-A" => "Anjali (". __('Female').")",
        "mr-IN-Standard-B" => "Aditya (". __('Male').")",
        "mr-IN-Standard-C" => "Dipti (". __('Female').")",
        "mr-IN-Wavenet-A" => "Gauri (". __('Female').")",
        "mr-IN-Wavenet-B" => "Harsh (". __('Male').")",
        "mr-IN-Wavenet-C" => "Ishita (". __('Female').")",
        "nb-NO-Standard-A" => "Ingrid (". __('Female').")",
        "nb-NO-Standard-B" => "Jonas (". __('Male').")",
        "nb-NO-Standard-C" => "Marit (". __('Female').")",
        "nb-NO-Standard-D" => "Olav (". __('Male').")",
        "nb-NO-Standard-E" => "Silje (". __('Female').")",
        "nb-NO-Wavenet-A" => "Astrid (". __('Female').")",
        "nb-NO-Wavenet-B" => "Eirik (". __('Male').")",
        "nb-NO-Wavenet-C" => "Inger (". __('Female').")",
        "nb-NO-Wavenet-D" => "Kristian (". __('Male').")",
        "nb-NO-Wavenet-E" => "Trine (". __('Female').")",
        "pl-PL-Standard-A" => "Agata (". __('Female').")",
        "pl-PL-Standard-B" => "Bartosz (". __('Male').")",
        "pl-PL-Standard-C" => "Kamil (". __('Male').")",
        "pl-PL-Standard-D" => "Julia (". __('Female').")",
        "pl-PL-Standard-E" => "Magdalena (". __('Female').")",
        "pl-PL-Wavenet-A" => "Natalia (". __('Female').")",
        "pl-PL-Wavenet-B" => "Paweł (". __('Male').")",
        "pl-PL-Wavenet-C" => "Tomasz (". __('Male').")",
        "pl-PL-Wavenet-D" => "Zofia (". __('Female').")",
        "pl-PL-Wavenet-E" => "Wiktoria (". __('Female').")",
        //"pt-BR-Neural2-A" => "Neural2 - FEMALE",
        //"pt-BR-Neural2-B" => "Neural2 - MALE",
        //"pt-BR-Neural2-C" => "Neural2 - FEMALE",
        "pt-BR-Standard-A" => "Ana (". __('Female').")",
        "pt-BR-Standard-B" => "Carlos (". __('Male').")",
        "pt-BR-Standard-C" => "Maria (". __('Female').")",
        "pt-BR-Wavenet-A" => "Julia (". __('Female').")",
        "pt-BR-Wavenet-B" => "João (". __('Male').")",
        "pt-BR-Wavenet-C" => "Fernanda (". __('Female').")",
        "pt-PT-Standard-A" => "Maria (". __('Female').")",
        "pt-PT-Standard-B" => "José (". __('Male').")",
        "pt-PT-Standard-C" => "Luís (". __('Male').")",
        "pt-PT-Standard-D" => "Ana (". __('Female').")",
        "pt-PT-Wavenet-A" => "Catarina (". __('Female').")",
        "pt-PT-Wavenet-B" => "Miguel (". __('Male').")",
        "pt-PT-Wavenet-C" => "João (". __('Male').")",
        "pt-PT-Wavenet-D" => "Marta (". __('Female').")",
        "pa-IN-Standard-A" => "Harpreet (". __('Female').")",
        "pa-IN-Standard-B" => "Gurpreet (". __('Male').")",
        "pa-IN-Standard-C" => "Jasmine (". __('Female').")",
        "pa-IN-Standard-D" => "Rahul (". __('Male').")",
        "pa-IN-Wavenet-A" => "Simran (". __('Female').")",
        "pa-IN-Wavenet-B" => "Amardeep (". __('Male').")",
        "pa-IN-Wavenet-C" => "Kiran (". __('Female').")",
        "pa-IN-Wavenet-D" => "Raj (". __('Male').")",
        "ro-RO-Standard-A" => "Maria (". __('Female').")",
        "ro-RO-Wavenet-A" => "Ioana (". __('Female').")",
        "ru-RU-Standard-A" => "Anastasia",
        "ru-RU-Standard-B" => "Alexander",
        "ru-RU-Standard-C" => "Elizabeth",
        "ru-RU-Standard-D" => "Michael",
        "ru-RU-Standard-E" => "Victoria",
        "ru-RU-Wavenet-A" => "Daria",
        "ru-RU-Wavenet-B" => "Dmitry",
        "ru-RU-Wavenet-C" => "Kristina",
        "ru-RU-Wavenet-D" => "Ivan",
        "ru-RU-Wavenet-E" => "Sophia",
        "sr-RS-Standard-A" => "Ana",
        "sk-SK-Standard-A" => "Mária (". __('Female').")",
        "sk-SK-Wavenet-A" => "Zuzana (". __('Female').")",
        //"es-ES-Neural2-A" => "Neural2 - FEMALE",
        //"es-ES-Neural2-B" => "Neural2 - MALE",
        //"es-ES-Neural2-C" => "Neural2 - FEMALE",
        //"es-ES-Neural2-D" => "Neural2 - FEMALE",
        //"es-ES-Neural2-E" => "Neural2 - FEMALE",
        //"es-ES-Neural2-F" => "Neural2 - MALE",
        "es-ES-Polyglot-1" => "Juan (". __('Male').")",
        "es-ES-Standard-A" => "María (". __('Female').")",
        "es-ES-Standard-B" => "José (". __('Male').")",
        "es-ES-Standard-C" => "Ana (". __('Female').")",
        "es-ES-Standard-D" => "Isabel (". __('Female').")",
        "es-ES-Wavenet-B" => "Pedro (". __('Male').")",
        "es-ES-Wavenet-C" => "Laura (". __('Female').")",
        "es-ES-Wavenet-D" => "Julia (". __('Female').")",
        //"es-US-Neural2-A" => "Neural2 - FEMALE",
        //"es-US-Neural2-B" => "Neural2 - MALE",
        //"es-US-Neural2-C" => "Neural2 - MALE",
        "es-US-News-D" => "Diego (". __('Male').")",
        "es-US-News-E" => "Eduardo (". __('Male').")",
        "es-US-News-F" => "Fátima (". __('Female').")",
        "es-US-News-G" => "Gabriela (". __('Female').")",
        "es-US-Polyglot-1" => "Juan (". __('Male').")",
        "es-US-Standard-A" => "Ana (". __('Female').")",
        "es-US-Standard-B" => "José (". __('Male').")",
        "es-US-Standard-C" => "Carlos (". __('Male').")",
        "es-US-Studio-B" => "Miguel (". __('Male').")",
        "es-US-Wavenet-A" => "Laura (". __('Female').")",
        "es-US-Wavenet-B" => "Pedro (". __('Male').")",
        "es-US-Wavenet-C" => "Pablo (". __('Male').")",
        "sv-SE-Standard-A" => "Ebba (". __('Female').")",
        "sv-SE-Standard-B" => "Saga (". __('Female').")",
        "sv-SE-Standard-C" => "Linnea (". __('Female').")",
        "sv-SE-Standard-D" => "Erik (". __('Male').")",
        "sv-SE-Standard-E" => "Anton (". __('Male').")",
        "sv-SE-Wavenet-A" => "Astrid (". __('Female').")",
        "sv-SE-Wavenet-B" => "Elin (". __('Female').")",
        "sv-SE-Wavenet-C" => "Oskar (". __('Male').")",
        "sv-SE-Wavenet-D" => "Hanna (". __('Female').")",
        "sv-SE-Wavenet-E" => "Felix (". __('Male').")",
        "ta-IN-Standard-A" => "Anjali (". __('Female').")",
        "ta-IN-Standard-B" => "Karthik (". __('Male').")",
        "ta-IN-Standard-C" => "Priya (". __('Female').")",
        "ta-IN-Standard-D" => "Ravi (". __('Male').")",
        "ta-IN-Wavenet-A" => "Lakshmi (". __('Female').")",
        "ta-IN-Wavenet-B" => "Suresh (". __('Male').")",
        "ta-IN-Wavenet-C" => "Uma (". __('Female').")",
        "ta-IN-Wavenet-D" => "Venkatesh (". __('Male').")",
        "-IN-Standard-A" => "Anjali - (". __('Female').")",
        "-IN-Standard-B" => "Karthik - (". __('Male').")",
        //"th-TH-Neural2-C" => "Neural2 - FEMALE",
        "th-TH-Standard-A" => "Ariya - (". __('Female').")",
        "tr-TR-Standard-A" => "Ayşe (". __('Female').")",
        "tr-TR-Standard-B" => "Berk (". __('Male').")",
        "tr-TR-Standard-C" => "Cansu (". __('Female').")",
        "tr-TR-Standard-D" => "Deniz (". __('Female').")",
        "tr-TR-Standard-E" => "Emre (". __('Male').")",
        "tr-TR-Wavenet-A" => "Gül (". __('Female').")",
        "tr-TR-Wavenet-B" => "Mert (". __('Male').")",
        "tr-TR-Wavenet-C" => "Nilay (". __('Female').")",
        "tr-TR-Wavenet-D" => "Selin (". __('Female').")",
        "tr-TR-Wavenet-E" => "Tolga (". __('Male').")",
        "uk-UA-Standard-A" => "Anya - (". __('Female').")",
        "uk-UA-Wavenet-A" => "Dasha - (". __('Female').")",
        //"vi-VN-Neural2-A" => "Neural2 - FEMALE",
        //"vi-VN-Neural2-D" => "Neural2 - MALE",
        "vi-VN-Standard-A" => "Mai (". __('Female').")",
        "vi-VN-Standard-B" => "Nam (". __('Male').")",
        "vi-VN-Standard-C" => "Hoa (". __('Female').")",
        "vi-VN-Standard-D" => "Huy (". __('Male').")",
        "vi-VN-Wavenet-A" => "Lan (". __('Female').")",
        "vi-VN-Wavenet-B" => "Son (". __('Male').")",
        "vi-VN-Wavenet-C" => "Thao (". __('Female').")",
        "vi-VN-Wavenet-D" => "Tuan (". __('Male').")",
    ];

    return $voiceNames[$hash] ?? $hash;
}


function format_double($number) {
    $parts = explode('.', $number);

    if ( count($parts) == 1 ) {
        return $parts[0] . '.0';
    }

    $integerPart = $parts[0];
    $decimalPart = isset($parts[1]) ? $parts[1] : '';

    if (strlen($decimalPart) > 1) {
        $secondDecimalPart = substr($decimalPart, 1);
    } else {
        $secondDecimalPart = '0';
    }

    return $integerPart . '.' . $decimalPart[0] . '.' . $secondDecimalPart;
}

function currencyShouldDisplayOnRight($currencySymbol) {
    return in_array($currencySymbol, config('currency.currencies_with_right_symbols'));
}

function getMetaTitle($setting){
	$lang = app()->getLocale();
    $settingTwo = SettingTwo::first();

    if($lang == $settingTwo->languages_default)
    {
        if(isset($setting->meta_title)) 
        {            
            $title = $setting->meta_title;
        } 
        else{
            $title = $setting->site_name . " | " . __('Home'); 
        } 
    }else{
        $meta_title = PrivacyTerms::where('type', 'meta_title')->where('lang', $lang)->first();
        if($meta_title){
            $title = $meta_title->content;
        }else{

            if(isset($setting->meta_title)) 
            {            
                $title = $setting->meta_title;
            } 
            else{
                $title = $setting->site_name . " | " . __('Home'); 
            } 
        }
    }

    return  $title;
}

function getMetaDesc($setting){
	$lang = app()->getLocale();
    $settingTwo = SettingTwo::first();

    if($lang == $settingTwo->languages_default)
    {
        if(isset($setting->meta_description)) 
        {            
            $desc = $setting->meta_description;
        } 
        else{
            $desc = "";
        } 
    }else{
        $meta_description = PrivacyTerms::where('type', 'meta_desc')->where('lang', $lang)->first();
        if($meta_description){
            $desc = $meta_description->content;
        }else{

            if(isset($setting->meta_description)) 
            {            
                $desc = $setting->meta_description;
            } 
            else{
                $desc = "";
            } 
        }
    }

    return  $desc;
}