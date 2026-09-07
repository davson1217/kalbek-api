<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\NpcLine;
use App\Models\Scenario;
use App\Models\Scene;
use App\Models\SceneProp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentTranslationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Scenario::query()
                ->with(['language', 'scenes.goals', 'scenes.npcLines', 'scenes.props'])
                ->get()
                ->each(fn (Scenario $scenario): bool => $this->seedScenario($scenario));
        });
    }

    private function seedScenario(Scenario $scenario): bool
    {
        $this->put($scenario, 'title', 'en', $scenario->title);
        $this->put($scenario, 'subtitle', 'en', $scenario->subtitle);
        $this->put($scenario, 'description', 'en', $scenario->description);

        $this->put($scenario, 'title', 'lt', $this->scenarioTitleLt($scenario));
        $this->put($scenario, 'subtitle', 'lt', $this->translate($scenario->subtitle));
        $this->put($scenario, 'description', 'lt', $this->translate($scenario->description));

        foreach ($scenario->scenes as $scene) {
            $this->seedScene($scenario, $scene);
        }

        return true;
    }

    private function seedScene(Scenario $scenario, Scene $scene): void
    {
        $title = $scene->title ?: str($scene->slug)->replace('-', ' ')->headline()->value();

        $this->put($scene, 'title', 'en', $title);
        $this->put($scene, 'setting', 'en', $scene->setting);
        $this->put($scene, 'title', 'lt', $this->sceneTitleLt($scenario->slug, $scene->slug, $title));
        $this->put($scene, 'setting', 'lt', $this->translate($scene->setting));

        foreach ($scene->goals as $goal) {
            $this->seedGoal($goal);
        }

        foreach ($scene->npcLines as $line) {
            $this->seedLine($scenario, $line);
        }

        foreach ($scene->props as $prop) {
            $this->seedProp($scenario, $prop);
        }
    }

    private function seedGoal(Goal $goal): void
    {
        $this->put($goal, 'label', 'en', $goal->label);
        $this->put($goal, 'intent', 'en', $goal->intent);
        $this->put($goal, 'label', 'lt', $this->translate($goal->label));
        $this->put($goal, 'intent', 'lt', $this->translate($goal->intent));
    }

    private function seedLine(Scenario $scenario, NpcLine $line): void
    {
        $this->put($line, 'support_translation', 'en', $line->support_translation);

        $translation = $scenario->language?->code === 'lt'
            ? $line->target_text
            : $this->translate($line->support_translation);

        $this->put($line, 'support_translation', 'lt', $translation);
    }

    private function seedProp(Scenario $scenario, SceneProp $prop): void
    {
        $this->put($prop, 'support_translation', 'en', $prop->support_translation);

        $translation = $scenario->language?->code === 'lt'
            ? $prop->target_text
            : $this->translate($prop->support_translation);

        $this->put($prop, 'support_translation', 'lt', $translation);
    }

    private function put(Model $model, string $field, string $locale, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '' || ! method_exists($model, 'translations')) {
            return;
        }

        $model->translations()->updateOrCreate(
            ['field' => $field, 'locale' => $locale],
            ['value' => $value],
        );
    }

    private function scenarioTitleLt(Scenario $scenario): string
    {
        if ($scenario->language?->code === 'lt') {
            return $scenario->title;
        }

        return $this->translate($scenario->title);
    }

    private function sceneTitleLt(string $scenarioSlug, string $sceneSlug, string $fallback): string
    {
        return $this->sceneTitlesLt()["{$scenarioSlug}:{$sceneSlug}"]
            ?? $this->translate($fallback);
    }

    private function translate(string $text): string
    {
        return $this->translations()[$text] ?? $text;
    }

    /**
     * @return array<string, string>
     */
    private function sceneTitlesLt(): array
    {
        return [
            'restoranas:atvykimas' => 'Atvykimas',
            'restoranas:sodinimas' => 'Sodinimas prie staliuko',
            'restoranas:meniu' => 'Meniu',
            'restoranas:gerimas' => 'Gėrimas',
            'restoranas:mokejimas' => 'Mokėjimas',
            'pharmacy-visit:greeting' => 'Pasisveikinimas',
            'pharmacy-visit:symptoms' => 'Simptomai',
            'pharmacy-visit:medicine' => 'Vaistai',
            'pharmacy-visit:payment' => 'Mokėjimas',
            'prisistatymas:pasisveikinimas' => 'Pasisveikinimas',
            'prisistatymas:is-kur' => 'Iš kur esate',
            'prisistatymas:kalbos' => 'Kalbos',
            'kavineje:uzsakymas' => 'Užsakymas',
            'kavineje:priedai' => 'Priedai',
            'kavineje:mokejimas' => 'Mokėjimas',
            'parduotuveje:ieskau' => 'Prekės paieška',
            'parduotuveje:kaina' => 'Kaina',
            'parduotuveje:kasa' => 'Kasa',
            'viesbutyje:registratura' => 'Registratūra',
            'viesbutyje:vardas' => 'Vardas',
            'viesbutyje:pusryciai' => 'Pusryčiai',
            'autobuse:bilietas' => 'Bilietas',
            'autobuse:kaina' => 'Kaina',
            'autobuse:stotele' => 'Stotelė',
            'mieste:pagalba' => 'Pagalba',
            'mieste:vieta' => 'Vieta',
            'mieste:patikslinimas' => 'Patikslinimas',
            'pas-gydytoja:kabinetas' => 'Kabinetas',
            'pas-gydytoja:klausimas' => 'Klausimas',
            'pas-gydytoja:patarimas' => 'Patarimas',
            'klaseje:pamoka' => 'Pamoka',
            'klaseje:nesuprantu' => 'Nesuprantu',
            'klaseje:pabaiga' => 'Pabaiga',
            'at-the-shop:looking-for-items' => 'Prekių paieška',
            'at-the-shop:price-and-bag' => 'Kaina ir maišelis',
            'at-the-shop:checkout' => 'Kasa',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        return [
            'At the restaurant' => 'Restorane',
            'Greet the waitress, ask for a table, order simple food and drink, and pay.' => 'Pasisveikinkite su padavėja, paprašykite staliuko, užsisakykite paprasto maisto ir gėrimo, tada sumokėkite.',
            'At the pharmacy' => 'Vaistinėje',
            'Greet the pharmacist, say a simple health problem, ask about medicine, and pay.' => 'Pasisveikinkite su vaistininku, pasakykite paprastą sveikatos problemą, paklauskite apie vaistus ir sumokėkite.',
            'Introducing yourself' => 'Prisistatymas',
            'Say your name, where you are from, and what language you speak.' => 'Pasakykite savo vardą, iš kur esate ir kokia kalba kalbate.',
            'At the cafe' => 'Kavinėje',
            'Order a drink, ask for sugar or milk, and pay.' => 'Užsisakykite gėrimą, paprašykite cukraus ar pieno ir sumokėkite.',
            'At the shop' => 'Parduotuvėje',
            'Ask where an item is, ask the price, and pay.' => 'Paklauskite, kur yra prekė, pasiteiraukite kainos ir sumokėkite.',
            'At the hotel' => 'Viešbutyje',
            'Check in, give your name, and ask about breakfast.' => 'Užsiregistruokite, pasakykite savo vardą ir paklauskite apie pusryčius.',
            'On the bus' => 'Autobuse',
            'Buy a ticket, say your destination, and ask for the stop.' => 'Nusipirkite bilietą, pasakykite kelionės tikslą ir paklauskite apie stotelę.',
            'In the city' => 'Mieste',
            'Ask where a place is and understand simple directions.' => 'Paklauskite, kur yra vieta, ir supraskite paprastas kryptis.',
            'At the doctor' => 'Pas gydytoją',
            'Say what hurts, answer a simple question, and thank the doctor.' => 'Pasakykite, ką skauda, atsakykite į paprastą klausimą ir padėkokite gydytojui.',
            'In the classroom' => 'Klasėje',
            'Greet the teacher, say you do not understand, and ask to repeat.' => 'Pasisveikinkite su mokytoja, pasakykite, kad nesuprantate, ir paprašykite pakartoti.',
            'Ask for simple items' => 'Paklauskite apie paprastas prekes',
            'Ask where an item is, ask the price, ask for a bag, and pay.' => 'Paklauskite, kur yra prekė, pasiteiraukite kainos, paprašykite maišelio ir sumokėkite.',

            'The learner enters a small restaurant. Rasa greets them at the door.' => 'Mokinys įeina į mažą restoraną. Rasa pasitinka jį prie durų.',
            'Rasa shows the learner a table.' => 'Rasa parodo mokiniui staliuką.',
            'The learner looks at a short menu.' => 'Mokinys žiūri į trumpą meniu.',
            'Rasa asks about a drink.' => 'Rasa paklausia apie gėrimą.',
            'The meal is finished. Rasa brings the bill.' => 'Valgis baigtas. Rasa atneša sąskaitą.',
            'The learner enters a pharmacy. Rasa is behind the counter.' => 'Mokinys įeina į vaistinę. Rasa yra už prekystalio.',
            'Rasa asks what is wrong.' => 'Rasa paklausia, kas negerai.',
            'Rasa shows a few simple medicines.' => 'Rasa parodo kelis paprastus vaistus.',
            'The learner is ready to pay.' => 'Mokinys pasiruošęs mokėti.',
            'Gabija meets the learner for the first time.' => 'Gabija pirmą kartą susitinka su mokiniu.',
            'Gabija asks where the learner is from.' => 'Gabija paklausia, iš kur mokinys yra.',
            'Gabija asks about languages.' => 'Gabija paklausia apie kalbas.',
            'Rasa is at the cafe counter.' => 'Rasa yra prie kavinės prekystalio.',
            'Rasa asks about additions to the drink.' => 'Rasa paklausia apie priedus prie gėrimo.',
            'The drink is ready and Rasa gives the price.' => 'Gėrimas paruoštas, ir Rasa pasako kainą.',
            'Gabija works in a small shop.' => 'Gabija dirba mažoje parduotuvėje.',
            'The learner has found the item.' => 'Mokinys rado prekę.',
            'The learner is at the checkout.' => 'Mokinys yra prie kasos.',
            'Rasa is at the hotel reception desk.' => 'Rasa yra prie viešbučio registratūros.',
            'Rasa needs the learner name.' => 'Rasai reikia mokinio vardo.',
            'The check-in is almost finished.' => 'Registracija beveik baigta.',
            'Gabija is the bus driver.' => 'Gabija yra autobuso vairuotoja.',
            'The driver names the price.' => 'Vairuotoja pasako kainą.',
            'The learner wants to know when to get off.' => 'Mokinys nori sužinoti, kada išlipti.',
            'Rasa is walking in the city.' => 'Rasa eina mieste.',
            'Rasa asks what place the learner needs.' => 'Rasa paklausia, kokios vietos mokiniui reikia.',
            'The learner checks the direction.' => 'Mokinys pasitikslina kryptį.',
            'Gabija is a doctor in a small clinic.' => 'Gabija yra gydytoja mažoje klinikoje.',
            'Gabija asks one simple health question.' => 'Gabija užduoda vieną paprastą sveikatos klausimą.',
            'Gabija gives simple advice.' => 'Gabija duoda paprastą patarimą.',
            'Rasa is a teacher starting a beginner Lithuanian class.' => 'Rasa yra mokytoja ir pradeda pradedančiųjų lietuvių kalbos pamoką.',
            'The teacher says a new word.' => 'Mokytoja pasako naują žodį.',
            'The short class is ending.' => 'Trumpa pamoka baigiasi.',
            'Emily works in a small shop. The learner wants to find a basic item.' => 'Emily dirba mažoje parduotuvėje. Mokinys nori rasti paprastą prekę.',
            'The learner has found the item and needs simple checkout help.' => 'Mokinys rado prekę ir jam reikia paprastos pagalbos prie kasos.',
            'The learner is at the checkout and is ready to pay.' => 'Mokinys yra prie kasos ir pasiruošęs mokėti.',

            'Ask for a table' => 'Paprašykite staliuko',
            'The learner greets the waitress and asks for a table.' => 'Mokinys pasisveikina su padavėja ir paprašo staliuko.',
            'Ask for a table for two' => 'Paprašykite staliuko dviem',
            'The learner asks for a table for two people.' => 'Mokinys paprašo staliuko dviem žmonėms.',
            'Thank her' => 'Padėkokite jai',
            'The learner thanks the waitress.' => 'Mokinys padėkoja padavėjai.',
            'Ask for the menu' => 'Paprašykite meniu',
            'The learner asks for the menu.' => 'Mokinys paprašo meniu.',
            'Order soup' => 'Užsisakykite sriubos',
            'The learner orders soup politely.' => 'Mokinys mandagiai užsisako sriubos.',
            'Order salad' => 'Užsisakykite salotų',
            'The learner orders salad politely.' => 'Mokinys mandagiai užsisako salotų.',
            'Order chicken' => 'Užsisakykite vištienos',
            'The learner orders chicken politely.' => 'Mokinys mandagiai užsisako vištienos.',
            'Order water' => 'Užsisakykite vandens',
            'The learner orders water.' => 'Mokinys užsisako vandens.',
            'Order tea' => 'Užsisakykite arbatos',
            'The learner orders tea.' => 'Mokinys užsisako arbatos.',
            'Say no drink' => 'Pasakykite, kad gėrimo nereikia',
            'The learner says they do not want a drink.' => 'Mokinys pasako, kad nenori gėrimo.',
            'Pay by card' => 'Mokėkite kortele',
            'The learner says they will pay by card.' => 'Mokinys pasako, kad mokės kortele.',
            'Thank and say goodbye' => 'Padėkokite ir atsisveikinkite',
            'The learner thanks the waitress and says goodbye.' => 'Mokinys padėkoja padavėjai ir atsisveikina.',
            'Ask for help' => 'Paprašykite pagalbos',
            'The learner greets the pharmacist and says they need help.' => 'Mokinys pasisveikina su vaistininku ir pasako, kad jam reikia pagalbos.',
            'Greet the pharmacist' => 'Pasisveikinkite su vaistininku',
            'The learner greets the pharmacist politely.' => 'Mokinys mandagiai pasisveikina su vaistininku.',
            'Say your head hurts' => 'Pasakykite, kad skauda galvą',
            'The learner says they have a headache.' => 'Mokinys pasako, kad jam skauda galvą.',
            'Say your throat hurts' => 'Pasakykite, kad skauda gerklę',
            'The learner says their throat hurts.' => 'Mokinys pasako, kad jam skauda gerklę.',
            'Say you have a cold' => 'Pasakykite, kad peršalote',
            'The learner says they have a cold.' => 'Mokinys pasako, kad peršalo.',
            'Ask the price' => 'Paklauskite kainos',
            'The learner asks how much the medicine costs.' => 'Mokinys paklausia, kiek kainuoja vaistas.',
            'Ask how to use it' => 'Paklauskite, kaip vartoti',
            'The learner asks how to take or use the medicine.' => 'Mokinys paklausia, kaip vartoti arba naudoti vaistą.',
            'Say you want to buy it' => 'Pasakykite, kad norite nusipirkti',
            'The learner says they want to buy the medicine.' => 'Mokinys pasako, kad nori nusipirkti vaistą.',
            'Pay in cash' => 'Mokėkite grynaisiais',
            'The learner says they will pay in cash.' => 'Mokinys pasako, kad mokės grynaisiais.',
            'The learner thanks the pharmacist and says goodbye.' => 'Mokinys padėkoja vaistininkui ir atsisveikina.',
            'Say your name' => 'Pasakykite savo vardą',
            'The learner says their name.' => 'Mokinys pasako savo vardą.',
            'Say where you are from' => 'Pasakykite, iš kur esate',
            'The learner says what country they are from.' => 'Mokinys pasako, iš kokios šalies yra.',
            'Say what language you speak' => 'Pasakykite, kokia kalba kalbate',
            'The learner says what language they speak.' => 'Mokinys pasako, kokia kalba kalba.',
            'Say you speak a little Lithuanian' => 'Pasakykite, kad truputį kalbate lietuviškai',
            'The learner says they speak a little Lithuanian.' => 'Mokinys pasako, kad truputį kalba lietuviškai.',
            'Order coffee' => 'Užsisakykite kavos',
            'The learner orders one coffee politely.' => 'Mokinys mandagiai užsisako vieną kavą.',
            'Ask for sugar' => 'Paprašykite cukraus',
            'The learner asks for sugar.' => 'Mokinys paprašo cukraus.',
            'Say no milk' => 'Pasakykite, kad pieno nereikia',
            'The learner says they do not want milk.' => 'Mokinys pasako, kad nenori pieno.',
            'The learner thanks the worker and says goodbye.' => 'Mokinys padėkoja darbuotojai ir atsisveikina.',
            'Ask where milk is' => 'Paklauskite, kur yra pienas',
            'The learner asks where the milk is.' => 'Mokinys paklausia, kur yra pienas.',
            'Ask where bread is' => 'Paklauskite, kur yra duona',
            'The learner asks where the bread is.' => 'Mokinys paklausia, kur yra duona.',
            'The learner asks how much it costs.' => 'Mokinys paklausia, kiek tai kainuoja.',
            'Ask for a bag' => 'Paprašykite maišelio',
            'The learner asks for a bag.' => 'Mokinys paprašo maišelio.',
            'The learner says thank you and goodbye.' => 'Mokinys padėkoja ir atsisveikina.',
            'Say you have a reservation' => 'Pasakykite, kad turite rezervaciją',
            'The learner says they have a reservation.' => 'Mokinys pasako, kad turi rezervaciją.',
            'Ask for a room' => 'Paprašykite kambario',
            'The learner says they need a room.' => 'Mokinys pasako, kad jam reikia kambario.',
            'Give your name' => 'Pasakykite savo vardą',
            'The learner says their name for check-in.' => 'Mokinys pasako savo vardą registracijai.',
            'Ask about breakfast' => 'Paklauskite apie pusryčius',
            'The learner asks when breakfast is.' => 'Mokinys paklausia, kada yra pusryčiai.',
            'Thank the receptionist' => 'Padėkokite registratorei',
            'The learner thanks the receptionist.' => 'Mokinys padėkoja registratorei.',
            'Ask for a ticket to the center' => 'Paprašykite bilieto į centrą',
            'The learner asks for one ticket to the city center.' => 'Mokinys paprašo vieno bilieto į miesto centrą.',
            'Ask for a ticket to the station' => 'Paprašykite bilieto į stotį',
            'The learner asks for one ticket to the station.' => 'Mokinys paprašo vieno bilieto į stotį.',
            'Pay for the ticket' => 'Sumokėkite už bilietą',
            'The learner says they will pay.' => 'Mokinys pasako, kad mokės.',
            'Ask if card is accepted' => 'Paklauskite, ar priima kortelę',
            'The learner asks if they can pay by card.' => 'Mokinys paklausia, ar galima mokėti kortele.',
            'Ask where to get off' => 'Paklauskite, kur išlipti',
            'The learner asks which stop they need.' => 'Mokinys paklausia, kuri stotelė jam reikalinga.',
            'Say thank you' => 'Padėkokite',
            'The learner thanks the driver.' => 'Mokinys padėkoja vairuotojai.',
            'The learner politely asks for help.' => 'Mokinys mandagiai paprašo pagalbos.',
            'Say you are lost' => 'Pasakykite, kad pasiklydote',
            'The learner says they are lost.' => 'Mokinys pasako, kad pasiklydo.',
            'Ask where the station is' => 'Paklauskite, kur yra stotis',
            'The learner asks where the station is.' => 'Mokinys paklausia, kur yra stotis.',
            'Ask where the pharmacy is' => 'Paklauskite, kur yra vaistinė',
            'The learner asks where the pharmacy is.' => 'Mokinys paklausia, kur yra vaistinė.',
            'Confirm the direction' => 'Pasitikslinkite kryptį',
            'The learner confirms they should go straight.' => 'Mokinys pasitikslina, ar turi eiti tiesiai.',
            'Thank the person' => 'Padėkokite žmogui',
            'The learner thanks the person for help.' => 'Mokinys padėkoja žmogui už pagalbą.',
            'Say yes, you have a fever' => 'Pasakykite, kad turite temperatūros',
            'The learner says yes, they have a fever.' => 'Mokinys pasako, kad taip, turi temperatūros.',
            'Say no fever' => 'Pasakykite, kad temperatūros nėra',
            'The learner says they do not have a fever.' => 'Mokinys pasako, kad temperatūros neturi.',
            'Ask how to take medicine' => 'Paklauskite, kaip vartoti vaistus',
            'The learner asks how to take the medicine.' => 'Mokinys paklausia, kaip vartoti vaistus.',
            'Thank the doctor' => 'Padėkokite gydytojui',
            'The learner thanks the doctor.' => 'Mokinys padėkoja gydytojui.',
            'Greet the teacher' => 'Pasisveikinkite su mokytoja',
            'The learner greets the teacher in the morning.' => 'Mokinys ryte pasisveikina su mokytoja.',
            'Say you are ready' => 'Pasakykite, kad esate pasiruošę',
            'The learner says they are ready.' => 'Mokinys pasako, kad yra pasiruošęs.',
            'Say you do not understand' => 'Pasakykite, kad nesuprantate',
            'The learner says they do not understand.' => 'Mokinys pasako, kad nesupranta.',
            'Ask to repeat' => 'Paprašykite pakartoti',
            'The learner asks the teacher to repeat.' => 'Mokinys paprašo mokytojos pakartoti.',
            'Thank the teacher' => 'Padėkokite mokytojai',
            'The learner thanks the teacher.' => 'Mokinys padėkoja mokytojai.',
            'Say goodbye' => 'Atsisveikinkite',
            'The learner says goodbye to the teacher.' => 'Mokinys atsisveikina su mokytoja.',
            'Ask where the milk is' => 'Paklauskite, kur yra pienas',
            'Ask where the bread is' => 'Paklauskite, kur yra duona',
            'The learner thanks the shop assistant and says goodbye.' => 'Mokinys padėkoja parduotuvės darbuotojai ir atsisveikina.',

            'Hello! Can I help you?' => 'Sveiki! Ar galiu padėti?',
            'Good morning. What are you looking for?' => 'Labas rytas. Ko ieškote?',
            'The milk is in the fridge on the left.' => 'Pienas yra šaldytuve kairėje.',
            'Of course. The milk is on the left.' => 'Žinoma. Pienas yra kairėje.',
            'The bread is over there, near the window.' => 'Duona yra ten, prie lango.',
            'Yes, the bread is near the window.' => 'Taip, duona yra prie lango.',
            'Milk' => 'Pienas',
            'Bread' => 'Duona',
            'Apples' => 'Obuoliai',
            'Water' => 'Vanduo',
            'Do you need anything else?' => 'Ar dar ko nors reikia?',
            'Did you find it? Great.' => 'Radote? Puiku.',
            'It costs one euro.' => 'Tai kainuoja vieną eurą.',
            'The price is one euro.' => 'Kaina yra vienas euras.',
            'Yes, we have bags.' => 'Taip, turime maišelių.',
            'Of course. Here is a bag.' => 'Žinoma. Štai maišelis.',
            'Please come to the checkout. How will you pay?' => 'Prašom prie kasos. Kaip mokėsite?',
            'That is one euro in total.' => 'Iš viso vienas euras.',
            'Thank you. Your payment is accepted.' => 'Ačiū. Jūsų mokėjimas priimtas.',
            'Great. Payment accepted.' => 'Puiku. Mokėjimas priimtas.',
            'You are welcome. See you!' => 'Prašom. Iki pasimatymo!',
            'Thank you. Have a nice day!' => 'Ačiū. Geros dienos!',
        ];
    }
}
