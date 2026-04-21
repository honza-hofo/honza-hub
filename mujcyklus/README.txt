MůjCyklus - Menstruační kalendář
==================================

Webová aplikace pro sledování menstruačního cyklu, plodných dnů,
nálad a symptomů. S AI analýzou a možností sdílení s partnerem.


CO APLIKACE UMÍ
----------------
- Kalendář s predikcí menstruace, ovulace a plodných dnů
- Záznam symptomů, nálad, intenzity a poznámek ke každému dni
- AI analýza cyklu (Claude Sonnet 4 přes Anthropic API)
- Sdílení vybraných dat s partnerem (unikátní odkaz)
- Email notifikace (připomínka menstruace, gynekologa)
- PWA (instalace na mobil jako appka)
- Tmavý režim, čeština + angličtina
- Export dat (JSON + PDF)
- GDPR compliant (souhlas se zpracováním, smazání účtu, export)


STRUKTURA SOUBORŮ
-----------------
landing.html    - Vstupní stránka (marketing, popis funkcí)
index.html      - Hlavní aplikace (kalendář, nastavení, AI chat)
partner.html    - Partnerský pohled (read-only, přes sdílecí kód)
api.php         - Backend API (všechny endpointy v jednom souboru)
config.php      - Konfigurace databáze a SMTP
install.php     - Vytvoření DB tabulek (po instalaci smazat!)
sw.js           - Service worker pro offline/PWA
manifest.json   - PWA manifest
icon-192.png    - Ikona 192x192
icon-512.png    - Ikona 512x512


TECHNOLOGIE
-----------
Frontend:   HTML + CSS + vanilla JavaScript (žádný framework)
Backend:    PHP 8+ s PDO (MySQL/MariaDB)
Font:       Nunito (Google Fonts)
AI:         Claude Sonnet 4 (Anthropic API, volitelné)
Email:      PHPMailer přes SMTP (vyžaduje composer install)
Design:     Růžová (#E8577D), levandulová (#E8E0FF), světle růžová (#FFE4EC)


API ENDPOINTY
-------------
Všechny endpointy jsou v api.php, volané přes ?action=xxx

Autentizace:
  POST ?action=register     - Registrace (email, heslo, jméno, věk)
  POST ?action=verify       - Ověření emailu kódem
  POST ?action=login        - Přihlášení
  POST ?action=logout       - Odhlášení
  GET  ?action=me           - Info o přihlášeném uživateli

Data:
  POST ?action=data         - Uložení dat cyklu (JSON)
  GET  ?action=data         - Načtení dat cyklu
  GET  ?action=export       - Export dat (JSON)

Partner:
  POST ?action=partner-share    - Vytvoření sdílecího odkazu
  GET  ?action=partner-share    - Info o sdílení
  DELETE ?action=partner-share  - Zrušení sdílení
  GET  ?action=partner-view     - Partnerský pohled (kód v URL)

AI:
  POST ?action=ai-analyze   - AI analýza cyklu (vyžaduje Anthropic API klíč)

Ostatní:
  POST ?action=reset-password   - Reset hesla
  DELETE ?action=account        - Smazání účtu
  POST ?action=cleanup          - Čištění starých dat (cron)


DATABÁZE
--------
5 tabulek (vytvoří install.php):
  mc_users            - Uživatelé (email, heslo bcrypt, jméno, věk)
  mc_user_data        - Data cyklu (JSON blob per uživatel)
  mc_login_attempts   - Rate limiting (IP + čas)
  mc_partner_shares   - Sdílecí kódy pro partnery
  mc_gdpr_consents    - GDPR souhlasy


INSTALACE NA HOSTING
--------------------
1. Nahrajte všechny soubory na hosting přes FTP
2. Spusťte: composer require phpmailer/phpmailer
3. Vytvořte MySQL databázi (např. "mujcyklus")
4. Upravte config.php:
   - DB_HOST, DB_NAME, DB_USER, DB_PASS
   - SMTP údaje (volitelné, pro email notifikace)
   - ANTHROPIC_API_KEY (volitelné, pro AI analýzu)
   - SITE_URL (vaše doména)
5. Otevřete v prohlížeči: https://vasedomena.cz/install.php
6. Po úspěšné instalaci SMAŽTE install.php!
7. Nastavte .htaccess pro mod_rewrite (Apache)

Požadavky:
  - PHP 7.4+ (doporučeno 8.0+)
  - MySQL 5.7+ nebo MariaDB 10.3+
  - mod_rewrite (Apache) nebo ekvivalent (nginx)
  - HTTPS (doporučeno, nutné pro PWA)
  - Composer (pro PHPMailer)


BEZPEČNOST
----------
- Hesla: bcrypt hash (cost 12)
- Session: 30 dnů, httponly, samesite=Lax, secure
- Rate limiting: max 10 pokusů za 15 minut (DB-based)
- CSRF ochrana přes session
- Prepared statements (PDO) proti SQL injection
- Věkové omezení 15+ (zákon 110/2019 Sb.)


PŘEDÁNÍ PROJEKTU
----------------
Pro předání jinému vývojáři:

1. Předejte kompletní složku se všemi soubory
2. Vývojář potřebuje:
   - PHP hosting s MySQL (Wedos, Forpsi, Endora...)
   - Přístup k FTP a phpMyAdmin
   - (Volitelně) Anthropic API klíč pro AI funkce
   - (Volitelně) SMTP účet pro email notifikace

3. Co může chtít upravit:
   - config.php — DB přístupy, SMTP, API klíče
   - landing.html — texty, obrázky, branding
   - index.html — barvy (CSS proměnné nahoře), funkce
   - api.php — business logika, limity

4. Žádné build nástroje, žádný npm/webpack/bundler.
   Vše je plain HTML + CSS + JS + PHP. Stačí nahrát a funguje.

5. Frontend je kompletně v jednom souboru (index.html ~2800 řádků).
   Všechny styly i skripty jsou inline — není co kompilovat.


AUTOR
-----
Vytvořeno s pomocí Claude AI pro osobní použití.
