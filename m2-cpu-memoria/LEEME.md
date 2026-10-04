# SysMonitor · M2 · CPU y Memoria

Copia el contenido de esta carpeta ENCIMA de la raíz de tu proyecto Laravel
(misma estructura de carpetas). Si ya instalaste el otro módulo, no hay conflicto:
los archivos compartidos (layout, app.css, routes/web.php) son idénticos en ambos.

Nota: reemplaza `routes/web.php` y `resources/css/app.css`. Si alguien del equipo
los modificó, mezcla los cambios a mano.

## Pasos

    cp -r app resources routes /ruta/al/Proyecto-Final-SO/
    cd /ruta/al/Proyecto-Final-SO
    npm run build
    php artisan serve --host=0.0.0.0 --port=8000

Abre http://localhost:8000/cpu-memoria

## Subir a GitHub

    git checkout -b modulo-m2
    git add app resources routes
    git commit -m "Agrega módulo M2 CPU y Memoria"
    git push -u origin modulo-m2
