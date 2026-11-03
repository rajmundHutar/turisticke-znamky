# Mobilní redesign aplikace Turistické známky

* **Author:** Rája Hutař
* **Date:** 2026-11-03

## Overview

Turistické známky (tz.rajmund.dev) je osobní aplikace pro jednoho uživatele. Slouží k evidenci sbírky turistických známek: v databázi je asi 3 100 známek, z toho asi 119 je sebraných. Aplikace je napsaná v Nette 3.3 a Latte 3.1, stránky se skládají na serveru, CSS je Bulma, k tomu malý ruční JavaScript (`www/js/bundle.js`) a mapa Leaflet s dlaždicemi Mapy.cz.

Dnes se aplikace na mobilu skoro nedá používat:

- Jedna karta známky zabírá celou obrazovku telefonu.
- Úvodní stránka má 200 karet na stránku a je vysoká asi 108 000 px.
- Karty nejsou zarovnané, protože názvy jsou různě dlouhé.
- Většina textů je anglicky.

Cílem je přestavět vzhled hlavně pro mobil: tmavý režim, zemité barvy, celé UI česky.

Stránky zůstávají:

- přehled všech známek,
- Moje sbírka,
- detail známky,
- Poblíž,
- statistiky.

Latte zůstává. CSS framework se smí vyměnit. Přechod na JS framework (Svelte) a API připadá v úvahu jen tehdy, když by jinak něco nešlo udělat.

Dva hlavní případy použití na mobilu:

1. **Na výletě:** otevřu Poblíž a vidím známky v okolí. Po klepnutí na známku se otevře detail, kde je i to, kde se dá koupit. Prodejní místa se nemíchají do seznamu Poblíž.
2. **Po koupi:** přidám známku do sbírky. Datum je předvyplněné na dnešek, což platí asi v 80 % případů, a můžu připsat poznámku. Známku najdu buď přes Poblíž, nebo doma podle čísla. Obojí je zhruba stejně časté.

## Analysis

Šest recenzentů prošlo kód a snímky obrazovky. Shodli se v jednom hlavním bodě: **většina cílů nevyžaduje nový framework ani velký redesign najednou.** Vyžaduje několik cílených změn. Nejdůležitější z nich se netýkají vzhledu, ale chování.

### 1. Přidání do sbírky už je na jedno klepnutí, ale tok kolem něj je rozbitý

Tento bod uvedlo všech šest recenzentů.

- `+` na kartě (`app/Presenters/templates/@stampCard.latte:26`) volá `CollectionModel::toggleCollect` (`app/Models/CollectionModel.php:52-71`). Ten známku vloží s dnešním datem. Případ „přidat s dnešním datem“ je tedy už dnes **jedno klepnutí**.
- Problém je s poznámkou: `+` → načtení celé stránky → tužka → dialog → psaní → Submit → další načtení. To jsou asi 3–4 klepnutí a 2 načtení stránky.
- **Na stránce Poblíž je úprava nejspíš úplně nefunkční.** Karty se tam vkládají přes `ajax()` pomocí `innerHTML` (`www/js/bundle.js:20-31`). Dialogy, výběr data a potvrzovací tlačítka se ale napojují jen jednou, při `DOMContentLoaded` (`bundle.js:1-8`). Tužka ani potvrzení „Disperse“ tedy na Poblíž nefungují.
- Po `+` na Poblíž proběhne přesměrování na `this#stamp-{id}` (`app/Presenters/Traits/CollectStamps.php:34`). Následuje nové načtení stránky, nový dotaz na polohu a nové AJAXové volání. Kotva se nenajde, protože karty dorazí až později.
- Stejný odkaz známku **přidává i odebírá** a jde o obyčejný GET bez ochrany. Omylem klepnuté nebo dvakrát klepnuté tlačítko venku tak může změnit sbírku a nedá se to vrátit zpět.
- Každá sebraná karta obsahuje vlastní skrytý `<dialog>` s formulářem (`@stampCard.latte:68-111`, `Multiplier` v `CollectStamps.php`). Při 200 kartách to znamená až 200 formulářů v jedné stránce.

**Závěr:** dřív než se začne řešit vzhled, je potřeba navrhnout jednu interakci „přidat do sbírky“. Měla by být oddělená od „odebrat“, fungovat i po AJAXovém překreslení a používat jeden sdílený dialog, který se plní daty vybrané známky. Recenzenti se rozcházejí v tom, jak má vypadat (viz Key Decisions, bod 3).

### 2. Výška stránek je hlavně jeden CSS řádek a velikost stránky

- `.stamp-image { height: 360px }` (`www/css/styles.css:6`)
- `column is-3`, které se na mobilu skládají pod sebe do jednoho sloupce
- číslo jako `title is-2`
- název s proměnnou délkou
- „ribbon“ s absolutní pozicí

K tomu 200 karet na stránku (`HomepagePresenter.php:29`).

Na Poblíž je vzdálenost až pod obrázkem a nad seznamem je nadpis se surovými souřadnicemi (`Nearby/default.latte:9`). Proto vzdálenost první známky není vidět bez posouvání.

**Závěr:** jedna kompaktní karta řeší najednou cíle „4–6 známek na obrazovku“, „Poblíž bez posouvání“ i „stejné prvky všude“. Karta by měla mít dvě podoby:

- řádek seznamu (Poblíž, výsledky hledání),
- dlaždice ve 2 sloupcích (přehled, sbírka).

Obrázek by měl mít pevný poměr stran (`aspect-ratio: 2/1`, nebo jen přední strana přes `object-fit`) a název by se měl zkracovat na pevný počet řádků. Velikost stránky snížit na zhruba 48–60.

### 3. Obrázky a mobilní data

Karty načítají plné PNG obou stran známky. Import je ukládá tak, jak přišly (`ImportModel.php:153-157`), a jedinou úsporou je `loading="lazy"`. Při 4–6 kartách na obrazovce venku na mobilních datech jsou to největší náklady.

**Doporučení:** při importu generovat náhledy, třeba WebP o šířce asi 320 px, a používat `srcset`.

Dál stojí za to zvážit, co se zbytečně načítá na každé stránce:

- loader Mapy.cz (`@layout.latte:33`), i když mapa používá Leaflet,
- Font Awesome 5.3 jako JS z CDN.

### 4. Bulma: ponechat, nebo nahradit?

V repozitáři je Bulma **0.9.1**. Nemá CSS proměnné ani tmavý režim. Používá se ručně, bez rendereru formulářů, asi na 54 místech v 9 souborech; nejvíc ve statistikách, stránkování a menu. Na třídách `navbar-burger`, `.modal` a `is-active` závisí i `bundle.js`.

- **Bulma 1.x** umí téma přes CSS proměnné a tmavý režim. Tmavé zemité barvy by pak byly hlavně přepsání několika proměnných. Verze 1.x ale odstranila `tile`, takže stránka statistik se bude muset přepsat v každém případě.
- **Náhrada za jiný framework** znamená přepsat třídy ve všech šablonách a nahradit `datepicker-bulma.min.css`. Plocha je malá, takže je to proveditelné, ale přínos oproti 1.x není jasný.
- Strukturální recenzent doporučuje definovat barvy jako **vlastní CSS proměnné v jednom souboru**, aby paleta nebyla svázaná s vnitřkem Bulmy. Upgrade Bulmy je pak samostatné rozhodnutí.

### 5. Žádný build krok, žádné SPA

- Docker image je `php:8.5-cli` bez Node. CSS i JS se commitují hotové.
- Nic z cílů nevyžaduje Svelte ani API:
  - Filtr bez tlačítka je pár řádků JS: `change` na výběru a odeslání GET formuláře se zpožděním při psaní. URL i stránkování zůstanou funkční.
  - Přidání bez načtení stránky a znovunapojení JS po překreslení zvládne **Naja** (AJAXová knihovna pro Nette), která nahradí vlastní `ajax()`. Snippety už existují (`NearbyPresenter.php:47-48`).
- Svelte s API by pro jednoho uživatele zdvojilo přihlašování, formuláře i logiku sbírky.

### 6. Čeština je levná, ale ne jen v `Translator.php`

`app/Helpers/Translator.php` je pevné pole asi 50 klíčů, skoro všechny anglicky. Neznámý klíč se tiše vypíše jako klíč (`:75`). Angličtina je ale i mimo překladač:

- `alert()` při chybě polohy (`Nearby/default.latte:32,35`)
- `confirm('Really commit action?')` (`bundle.js:155`)
- zavírací tlačítko dialogu s textem „asd“ (`@stampCard.latte:107`)
- `aria-label` „Goto page“ ve stránkování
- chybové stránky 4xx/5xx
- výchozí hlášky `netteForms.js`
- výběr data bez české lokalizace
- tabulky v administraci
- `<html>` bez atributu `lang`

Přepnutí do češtiny rozbije testy: `TranslatorTest.phpt:14,26,27`, `StampPresenterTest.phpt:341,349`, `LoginPresenterTest.phpt:91`. Patří do samostatného PR spolu s testem, který ověří, že každý klíč použitý v šablonách existuje.

### 7. Poblíž venku

- `getCurrentPosition` se volá bez `timeout` a `maximumAge`.
- Při chybě se zobrazí anglický `alert()`.
- Chybí stav „hledám polohu“.
- `ajax()` neošetřuje chybu sítě ani odpověď, která není JSON, takže stránka prostě zůstane prázdná.

**Potřeba:**

- český stav přímo ve stránce (hledám polohu / poloha nepovolena / chyba) s tlačítkem „zkusit znovu“,
- záložní cesta: hledání podle čísla,
- vzdálenost a stav sebráno/nesebráno v prvním řádku položky.

### 8. Tmavý režim a slunce

Tmavý zemitý motiv s nízkým kontrastem se na přímém slunci špatně čte, a hlavní použití je právě venku. Je potřeba stanovit minimální kontrast:

- WCAG AA (alespoň 4,5 : 1) pro text,
- ideálně 7 : 1 pro vzdálenost a stav sebráno.

Stav nesmí být vyjádřen jen barvou. Dnes je to zelené `+` a ribbon.

Dál je potřeba:

- opravit `theme-color` `#ffffff` v `@layout.latte:12`,
- ověřit, jak v tmavém motivu vypadají dlaždice Mapy.cz a modré značky prodejních míst (`#3273dc`).

### 9. Navigace a pořadí na detailu

**Navigace:** hamburger skrývá 4 cíle a k tomu přihlášení a administraci. Návrh je spodní lišta se 3–4 záložkami:

- Poblíž,
- Známky (s hledáním),
- Sbírka,
- případně Statistiky, nebo jejich souhrn nahoře ve Sbírce.

Přihlášení a administrace by šly do malé nabídky v hlavičce. „Moje sbírka“ zůstává jedna šablona s filtrem `onlyCollected`.

**Detail:** dnešní pořadí je obrázek, název, dlouhý popis, odkazy, prodejní místa, formulář, mapa 500 px a 20 karet okolních známek. Navržené pořadí:

1. číslo, název, stav a tlačítko přidat/upravit
2. malý obrázek
3. kde koupit
4. mapa
5. popis sbalený na asi 3 řádky
6. odkazy
7. známky poblíž

Tím se vyřeší i odložená položka „popis odtlačí mapu“.

### 10. Hledání podle čísla

Hledání existuje (`StampsModel.php:39-43`): `id LIKE 'n%' OR name LIKE '%n%'`, řazeno podle čísla. Přesná shoda je tedy první, ale „12“ najde i 120–129 a 1200–1299. Navíc je potřeba klepnout na Submit.

**Doporučení:** když zadané číslo přesně odpovídá existující známce, přejít rovnou na její detail. Pole by mělo mít `inputmode="numeric"`.

### 11. Cíle zatím nejdou ověřit

Kritéria „4–6 známek na obrazovku“ a „bez posouvání“ potřebují pevně určené:

- rozměr obrazovky (např. 390 × 844 CSS px, iPhone 13, na výšku),
- co se počítá jako „viditelná známka“,
- testovací data.

Kritérium „2–3 klepnutí“ potřebuje definici klepnutí (počítá se psaní? zavření klávesnice?) a dvě samostatná měřítka:

- „přidat s dnešním datem“ (cíl: 1 klepnutí),
- „přidat s poznámkou nebo jiným datem“.

Testy s databází ani v prohlížeči neexistují. Ověřování bude buď ruční kontrolní seznam, nebo Playwright, který je na hostiteli nainstalovaný.

### 12. Nasazení přes FTPS a cache

Nové CSS nemá v URL verzi, takže telefony můžou držet starou Bulmu a k ní novou HTML strukturu. Je potřeba přidat `?v=` podle času změny nebo hashe souboru. V tiketu je potřeba také říct, jestli se po odebrání `bulma.min.css` soubor smaže i na serveru.

## Goals vs. Non-Goals

* **In-Scope Goals:**
  - Na mobilu (390 × 844) je v přehledu a ve sbírce vidět aspoň 4–6 známek, zarovnaných v mřížce nebo seznamu (obrázek s pevným poměrem stran, název zkrácený na pevný počet řádků).
  - Na Poblíž jsou bez posouvání vidět vzdálenost a stav sebráno/nesebráno první známky. Stránka má český stav pro hledání polohy a pro chybu.
  - Přidání do sbírky s dnešním datem je 1 klepnutí. Přidání s poznámkou zvládnou nejvýš 2–3 klepnutí plus psaní, a to z Poblíž i z hledání podle čísla, bez celého načtení stránky.
  - Odebrání ze sbírky je oddělené od přidání a vyžaduje potvrzení.
  - Filtr se použije bez tlačítka Submit. URL a stránkování fungují dál.
  - Hledání přesného čísla vede rovnou na detail známky.
  - Celé UI je česky, včetně JS hlášek, chybových stránek, výběru data, `aria-label` a `<html lang="cs">`.
  - Tmavý motiv se zemitými barvami a s minimálním kontrastem čitelným na slunci.
  - Na detailu je přidání a „kde koupit“ nad popisem a popis je sbalený.
  - Náhledy obrázků místo plných PNG v kartách. Menší stránka než 200 karet.
  - V kartě sebrané známky a v dialogu přidání je místo pro budoucí vlastní fotku (#19).
* **Out-of-Scope Non-Goals:**
  - #18 „Známka dne“ a #19 vlastní fotka; u #19 jen vyhrazené místo, žádné nahrávání.
  - Přechod na Svelte nebo jiné SPA a API.
  - Build krok s Node v Dockeru, pokud se neukáže jako nutný.
  - Přepínač světlý/tmavý režim.
  - Detailní rozvržení pro desktop nad rámec jednoho pravidla o maximální šířce.
  - Animace, příběh značky, kompletní design systém.
  - Designový dokument. Rozhodnutí autora (2026-11-03): u hobby aplikace pro jednoho uživatele nedává smysl, jednotnost zajistí sdílené Latte bloky (`{define}`/`{include}`) a jeden soubor s CSS proměnnými.
  - Restrukturalizace formulářů podle #37 (BaseForm/FormFactory). Leda by se rozhodlo jinak, viz Key Decisions, bod 9.

## Alternatives Weighed

- **Svelte + API místo Latte.** Recenzenti doporučují zamítnout. Žádný cíl není serverovým vykreslováním blokovaný, živý filtr i přidání bez načtení stránky zvládne Naja nebo pár řádků JS. SPA by zdvojilo přihlašování, formuláře i logiku sbírky pro jediného uživatele. Autor sám chtěl JS framework jen v případě blokace.
- **Úplná výměna CSS frameworku (Tailwind, Pico apod.).** Recenzenti ji nedoporučují jako první krok. Znamená přepsat třídy ve všech šablonách a CSS výběru data a Node build krok by komplikoval nasazení přes FTPS. Zůstává otevřená, pokud by se vybraný vizuální návrh s Bulmou „pral“.
- **Velký redesign najednou** (rozvržení → varianty → dokument → všechny stránky). Recenzenti ho nedoporučují. Celá hodnota by čekala na dokončení designové fáze a u hobby projektu jednoho člověka je to typický způsob, jak práce uvízne. Místo toho samostatně nasaditelné PR, kde každé něco zlepší.
- **Designový dokument.** Rejected by author (2026-11-03): u aplikace pro jednoho uživatele nedává smysl, jednotnost zajistí sdílené Latte bloky a CSS proměnné. Recenzenti předtím nedoporučovali psát ho předem a podrobně.
- **Sledovat `prefers-color-scheme` místo jen tmavého režimu.** Navrhl to recenzent pro srozumitelnost kvůli čitelnosti na slunci. Autor připustil čistě tmavý režim. Rozhodnout po vyzkoušení venku.
- **Nový průvodce přidáním známky (wizard).** Recenzenti ho nedoporučují: přidání s dnešním datem už je na jedno klepnutí, chybí jen snadná cesta k poznámce.

## Key Decisions & Staging for Next Steps

**Otevřená rozhodnutí, která je potřeba udělat před kódováním:**

1. **Bez build kroku, nebo s ním?** Doporučení: zůstat bez buildu, s ručně psaným CSS s vlastními proměnnými a commitovanými soubory. Přidat verzování URL statických souborů (`?v=`).

   **Rozhodnuto (2026-11-03): bez build kroku**, verzování URL statických souborů (`?v=`).
2. **Bulma 0.9 → 1.x, nebo vlastní CSS?** V obou případech se přepisuje stránka statistik (`tile`). Doporučení: barvy jako vlastní CSS proměnné v jednom souboru, upgrade Bulmy jako samostatný krok.

   **Rozhodnuto (2026-11-03, autorovi je to jedno, doporučení): Bulma 1.x** (tmavý režim a CSS proměnné bez buildu); barvy jako vlastní proměnné v jednom souboru. Pokud se vybraný vzhled s Bulmou „pere“, přejít později na vlastní CSS.
3. **Jak přesně funguje přidání do sbírky.** Recenzenti navrhli dvě varianty:
   - (a) `+` hned uloží s dnešním datem, ukáže „Přidáno – přidat poznámku / vrátit“ a teprve pak nabídne poznámku;
   - (b) `+` otevře sdílený dialog s předvyplněným dneškem a polem pro poznámku, uloží se druhým klepnutím.

   Varianta (a) je rychlejší pro 80 % případů, (b) je jednodušší a nemá stav „vrátit“.

   **Rozhodnuto autorem (2026-11-03): varianta (b)** – `+` otevře sdílený dialog s předvyplněným dneškem a poznámkou, uloží se druhým klepnutím. V obou případech: odebrání zvlášť a s potvrzením, změna přes POST nebo AJAX, ne přes GET odkaz, a jeden sdílený dialog místo formuláře v každé kartě.
4. **Naja, nebo vlastní `ajax()`.** Doporučení: Naja, kvůli znovunapojení JS po překreslení snippetu.
5. **Podoba karty:** řádek seznamu i dlaždice ve 2 sloupcích. Ukazovat obě strany známky, nebo jen přední? Kam patří stav sebráno místo ribbonu a kam vzdálenost?
6. **Navigace:** spodní lišta (Poblíž / Známky / Sbírka / Statistiky?), nebo hamburger. Kam patří hledání podle čísla: do hlavičky záložky Známky.

   **Rozhodnuto autorem (2026-11-03): spodní lišta** v pořadí Poblíž · Známky · Sbírka · Statistiky; přihlášení, admin a odhlášení jako malá ikona v hlavičce.
7. **Velikost stránky** (48–60?) a náhledy obrázků (WebP asi 320 px generované při importu).

   **Výchozí volba (2026-11-03, lze změnit):** 60 známek na stránku; náhledy WebP ~320 px generované při importu obrázků (ověřit dostupnost GD/WebP v Dockeru i na Wedosu).
8. **Jak se ověří kritéria:** rozměr obrazovky 390 × 844, testovací data a nástroj (ruční kontrolní seznam, nebo Playwright).

   **Výchozí volba (2026-11-03):** Playwright snímky v 390 × 844 proti lokální kopii produkční DB, u každého PR před a po.
9. **Vztah k #37:** buď nejdřív zjednodušit formuláře, nebo se dohodnout, že redesign formuláře jen přestyluje a jeden sdílený dialog vznikne bez restrukturalizace.

   **Výchozí volba (2026-11-03):** redesign formuláře jen přestyluje; sdílený dialog přidání vznikne bez restrukturalizace podle #37 (#37 zůstává odložené do redesignu jako samostatný úklid).

**Rozhodnuto autorem (2026-11-03): malé samostatné PR místo velkého redesignu, bez designového dokumentu.**

**Navržené pořadí PR (každý jde nasadit samostatně, stav lze vrátit nasazením předchozího commitu z `main`):**

1. **Čeština:** hodnoty v `Translator.php`, JS hlášky, chybové stránky, lokalizace výběru data, `lang="cs"`, oprava testů a test, že klíče existují. Nezávisí na designu a sám splní cíl „žádná angličtina“.
2. **Přidání do sbírky:** oddělit přidání a odebrání, jeden sdílený dialog, Naja, oprava funkčnosti na Poblíž, přesné číslo vede na detail. Včetně testů presenterů.
3. **Kompaktní karta a menší stránka:** `@stampCard.latte`, `styles.css` a `StampsPerPage`. Tady má smysl ukázat 2–3 vizuální varianty právě karty.
4. **Filtr bez Submit** (automatické odeslání GET formuláře).
5. **Poblíž:** stavy polohy, bez souřadnic v nadpisu. **Detail:** nové pořadí a sbalený popis.
6. **Náhledy obrázků** a zeštíhlení načítaných skriptů.
7. **Barvy a tmavý motiv** (CSS proměnné, případně Bulma 1.x, přepis statistik), navigace, `theme-color`, kontrola mapy.
**Vybraný vzhled (autor, 2026-11-03), plátno „Turistické známky – redesign“, řádek D:**

- **Rozložení z varianty B:** přehled jako řádky seznamu (náhled 92 × 52 px, číslo, název na jeden řádek, datum sebrání, vpravo kulaté tlačítko 44 px „+“ nebo fajfka). Filtr jako segmentový přepínač Vše / Mám / Nemám s počty, hledání podle čísla ikonou v hlavičce. Detail: obrázek nahoře přes celou šířku, číslo a název, blok „Ve sbírce od … + poznámka + Upravit“ nebo tlačítko „Přidat do sbírky“, Kde koupit, sbalený popis „O místě“.
- **Přidání:** spodní panel (bottom sheet) s datem předvyplněným na dnešek, nepovinnou poznámkou a tlačítkem „Uložit do sbírky“.
- **Barvy z varianty C (břidlice a mosaz):** pozadí `#161819`, plocha `#202326`, plocha obrázku `#2a2d31`, okraj `#30343a`, oddělovač řádků `#26292d`, text `#e9e6df`, tlumený text `#a7a59e`, akcent `#d4ab5a` (text na akcentu `#161819`), akcent jako text/odkaz `#e0bd72`, blok „ve sbírce“ `#26231c` s okrajem `#5a4a26`, spodní lišta `#1c1e20`.
- **Písmo z varianty C:** IBM Plex Sans (400/500/600/700).
- **Spodní lišta:** Poblíž · Známky · Sbírka · Statistiky, aktivní položka v barvě `#e0bd72`.
- **Poblíž:** stejné řádky jako Známky, seřazené od nejbližší; pod názvem vzdálenost (mosazně, s ikonou špendlíku), v hlavičce stav polohy („Poloha zjištěna · přesnost ±15 m“) a tlačítko pro nové zjištění polohy, filtr Vše / Mám / Nemám (výchozí Nemám).

**K procesu výběru vzhledu:** varianty z Claude Design je dobré hodnotit proti kritériím výše (počet známek na obrazovku, kontrast na slunci, velikost klepacích ploch alespoň 44 px), nejen podle dojmu. Jako podklad stačí statické HTML se skutečným CSS a snímek obrazovky v 390 × 844.
