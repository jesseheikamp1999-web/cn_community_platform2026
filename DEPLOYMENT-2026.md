# CN Community update uitrollen

Voer na het uploaden op Cloud86/Plesk uit:

```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan cn:index-nomi-knowledge
php artisan optimize:clear
```

De migratie maakt de Academy-vragenbank en toetspogingen aan en vult de staffopleidingen direct. Er is geen losse importknop nodig.

- Award-rondes bepalen automatisch de actuele fase, deadline en volgende fase.
- Gebruikers met `content.manage` werken als CN-redacteur en kunnen nieuws maken, plannen en publiceren.
- De eigenaar beheert rollen en permissies via `MijnCN > Rollen & permissies`.
- Handmatig ingestelde permissies worden bij een volgende Discord-login niet overschreven.
- Nomi indexeert uitsluitend gepubliceerde platforminhoud. Privéberichten, toetsantwoorden en HR-data worden niet als AI-kennis gebruikt.

Laat voor geplande publicaties iedere minuut deze cronjob draaien:

```bash
php artisan cn:publish-scheduled
```
