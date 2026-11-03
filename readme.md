[Turistické známky](https://tz.rajmund.dev)
=================

Web pro moje sbírání turistických známek.

Lokální vývoj
-------------

Vše běží v Dockeru.

1. Zkopírovat `app/config/local.neon.dist` do `app/config/local.neon` (přístup k DB v Dockeru, není potřeba nic měnit) a doplnit `mapyApiKey` – API klíč Mapy.cz pro mapu na detailu známky ([developer.mapy.com](https://developer.mapy.com/), v účtu omezit na Referrers, např. `localhost`).
2. `docker compose up` – aplikace na http://localhost:8082, MySQL na portu 13306 (uživatel `root`, heslo `root`). Schéma se vytvoří z `.docker/db/init.sql` jen při prvním startu; data zůstávají ve volume `db-data` i po `docker compose down`. Úplně čistou DB dá `docker compose down -v`.
3. `docker compose run --rm app composer install`
4. Registrace neexistuje, uživatele je potřeba vložit do DB ručně. Hash hesla:

   ```
   docker compose run --rm app php -r 'echo password_hash("heslo", PASSWORD_DEFAULT), "\n";'
   ```

   a pak (např. `docker compose exec db mysql -uroot -proot tz`):

   ```sql
   INSERT INTO tz_users (email, password, role) VALUES ('admin@example.com', '<hash>', 'admin');
   ```

   Role `admin` je potřeba pro administraci, běžný uživatel má roli `member`.
5. Přihlásit se a v administraci (`/admin/`) spustit „Importovat známky“ a potom „Importovat obrázky“ (stahuje se po dávkách, tlačítko je potřeba spustit vícekrát).
