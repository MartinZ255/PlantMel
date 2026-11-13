<?php

/*
|--------------------------------------------------------------------------
| API-Routen
|--------------------------------------------------------------------------
|
| Bewusst leer (F-16, Entscheidung E6, Slice 1b): Die frueheren Routen
| (/api/categories, /api/rating-dimensions, /api/recipes...) wurden vom
| Frontend nicht genutzt, zeigten teils auf fehlende Controller-Methoden
| (500) und waren ohne Session-Middleware fuer niemanden bedienbar.
|
| Die Datei bleibt registriert (bootstrap/app.php), damit kuenftige
| API-Routen hier ergaenzt werden koennen. Neue Routen brauchen einen
| passenden Guard und einen Eintrag in
| tests/Feature/Authorization/RouteRoleMatrixTest.php.
|
*/
