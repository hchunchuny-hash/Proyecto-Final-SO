# SysMonitor Web

Aplicación web para observar y administrar, desde el navegador, el sistema operativo Linux donde está instalada. Obtiene información real del kernel (procesos, CPU, memoria, discos y usuarios) leyendo `/proc` y ejecutando comandos del sistema de forma controlada. Incluye simuladores de planificación de CPU e interbloqueos.

Proyecto final del curso de Sistemas Operativos 1, Universidad Mariano Gálvez de Guatemala, Centro de Estudios Chimaltenango. Catedrático: Ing. Josué Jiménez.

**URL pública:** [https://tu-dominio.ejemplo]

## Integrantes y módulos

| Integrante | Módulo | Responsabilidad |
|---|---|---|
| Arnold | M1 Procesos | Listado y administración de procesos reales |
| Dany | M2 CPU y Memoria | Uso de CPU, RAM, swap y carga |
| Henry | M3 Planificación | Simulador de algoritmos con diagrama de Gantt |
| Griselda | M4 Interbloqueos | Algoritmo del banquero y grafo de asignación |
| Marisol | M5 Almacenamiento | Discos, particiones, sistemas de archivos y usuarios |
| Henry | M6 Integración y Bitácora | Login con roles, layout, bitácora, CommandRunner y despliegue |

## Tecnologías

Debian, Apache 2, PHP, Laravel, SQLite, Blade y CSS propio.

## Requisitos

- Debian con Apache 2
- PHP 8.3 o superior (requerido por Laravel 13) con las extensiones `sqlite3`, `mbstring`, `xml`, `curl` y `zip`
- Composer
- Git

## Instalación en desarrollo

```bash
git clone https://github.com/hchunchuny-hash/Proyecto-Final-SO.git
cd Proyecto-Final-SO
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Agrega al final del archivo `.env` las contraseñas de las cuentas de demostración (el administrador las proporciona por privado):

```
ADMIN_PASSWORD="..."
OBSERVER_PASSWORD="..."
```

Crea los usuarios y arranca la aplicación:

```bash
php artisan db:seed --class=UsuariosSeeder
php artisan serve --host=0.0.0.0
```

## Despliegue en producción

Los pasos para publicar la aplicación en un VPS con Apache, dominio, HTTPS y firewall están en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

## Roles

| Rol | Permisos |
|---|---|
| Administrador | Ve todos los módulos y puede enviar señales y cambiar prioridades |
| Observador | Solo lectura |

El registro de usuarios está deshabilitado. Las cuentas de demostración se entregan al catedrático por separado.

## Seguridad

- Los comandos del sistema se ejecutan únicamente con `App\Services\CommandRunner`: lista blanca, sin shell y con validación de datos.
- Las señales y `renice` solo se aplican a procesos de prueba lanzados por la propia aplicación.
- Toda acción administrativa requiere rol Administrador y queda registrada en la bitácora.
- Las contraseñas y la clave de la aplicación están en `.env`, que no se sube a GitHub.

## Flujo de trabajo en Git

- Cada módulo se desarrolla en su propia rama (`feature/mX-nombre`).
- Los cambios entran a `main` mediante pull request revisado por otro integrante.
- No se hace push directo a `main`.

## Uso de herramientas de inteligencia artificial

Se utilizó Claude (Anthropic) como apoyo para explicar conceptos, preparar borradores de código y redactar documentación. Todo el código fue revisado y probado, y cada integrante es responsable de entender y defender su módulo.

## Capturas de pantalla

[Agregar capturas de inicio, login, bitácora y de cada módulo]
