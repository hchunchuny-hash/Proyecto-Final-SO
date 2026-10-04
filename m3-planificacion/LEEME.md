# SysMonitor · M3 · Planificación

Copia el contenido de esta carpeta ENCIMA de la raíz de tu proyecto Laravel
(misma estructura de carpetas). Si ya instalaste el otro módulo, no hay conflicto:
los archivos compartidos (layout, app.css, routes/web.php) son idénticos en ambos.

Nota: reemplaza `routes/web.php` y `resources/css/app.css`. Si alguien del equipo
los modificó, mezcla los cambios a mano.

## Pasos

    cp -r app resources routes tests /ruta/al/Proyecto-Final-SO/
    cd /ruta/al/Proyecto-Final-SO
    npm run build
    php artisan serve --host=0.0.0.0 --port=8000

Abre http://localhost:8000/planificacion

## Subir a GitHub

    git checkout -b modulo-m3
    git add app resources routes tests
    git commit -m "Agrega módulo M3 Planificación"
    git push -u origin modulo-m3

## Tests (opcional)

    php artisan test --filter=PlanificadorTest
