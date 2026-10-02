# Guía de instalación y publicación en un VPS Debian

Pasos para desplegar SysMonitor Web desde cero en un servidor Debian con Apache, dominio, HTTPS y firewall. Reemplaza `midominio.com` por el dominio real y `TU_USUARIO` por el usuario del servidor.

## 1. Servidor y dominio

1. Una sola persona del grupo crea la cuenta del proveedor (por ejemplo Azure for Students) y crea una VM con Debian. Los demás acceden por SSH con su propio usuario.
2. Apunta el dominio (o subdominio de DuckDNS) a la IP pública del servidor con un registro DNS tipo A. Verifica con `ping midominio.com`.
3. En el panel del proveedor, abre los puertos 22, 80 y 443.

## 2. Acceso seguro por SSH

En tu computadora genera una llave y cópiala al servidor:

```bash
ssh-keygen -t ed25519
```

Agrega el contenido de la llave pública (`.pub`) al archivo `~/.ssh/authorized_keys` del usuario en el servidor. **Comprueba en otra ventana que puedes entrar con la llave** antes de seguir, y luego edita `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sudo systemctl restart ssh
```

## 3. Firewall

Permite SSH **antes** de activar el firewall, para no quedarte fuera:

```bash
sudo apt update && sudo apt install -y ufw
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

## 4. Software necesario

```bash
sudo apt install -y apache2 php php-cli php-sqlite3 php-mbstring php-xml php-curl php-zip libapache2-mod-php sqlite3 git unzip curl composer
php -v
```

## 5. Código de la aplicación

```bash
sudo mkdir -p /var/www/sysmonitor
sudo chown $USER:www-data /var/www/sysmonitor
git clone https://github.com/hchunchuny-hash/Proyecto-Final-SO.git /var/www/sysmonitor
cd /var/www/sysmonitor
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edita `.env` con `nano .env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://midominio.com
ADMIN_PASSWORD="..."
OBSERVER_PASSWORD="..."
```

El archivo `.env` nunca se sube a GitHub. Crea la base de datos y los usuarios:

```bash
php artisan migrate --force
php artisan db:seed --class=UsuariosSeeder
```

## 6. Permisos

Apache corre como `www-data` y necesita escribir en estas carpetas (sin darle permisos sudo):

```bash
sudo chgrp -R www-data storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache database
```

## 7. Apache (VirtualHost)

```bash
sudo nano /etc/apache2/sites-available/sysmonitor.conf
```

```apache
<VirtualHost *:80>
    ServerName midominio.com
    DocumentRoot /var/www/sysmonitor/public

    <Directory /var/www/sysmonitor/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sysmonitor-error.log
    CustomLog ${APACHE_LOG_DIR}/sysmonitor-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo a2dissite 000-default
sudo a2ensite sysmonitor
sudo apachectl configtest
sudo systemctl reload apache2
```

## 8. HTTPS con Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d midominio.com
sudo certbot renew --dry-run
systemctl list-timers | grep certbot
```

Elige la opción de redirigir HTTP a HTTPS. El certificado dura 90 días y `certbot.timer` lo renueva solo.

## 9. Verificación

- La URL `https://midominio.com` abre el login con el candado.
- Entra como Administrador y como Observador y comprueba los permisos.
- La bitácora registra las acciones.
- `sudo ufw status` muestra solo los puertos 22, 80 y 443.

Evidencias para el manual técnico: captura del candado en el navegador y la salida de `sudo certbot certificates`.

## 10. Actualizar la aplicación

```bash
cd /var/www/sysmonitor
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

## 11. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| Error 500 | Permisos o `.env` incompleto | Revisar los permisos del paso 6 y `storage/logs/laravel.log` |
| Error 404 en todas las rutas menos `/` | `mod_rewrite` desactivado | `sudo a2enmod rewrite` y recargar Apache |
| `attempt to write a readonly database` | `database/` sin permiso de escritura para `www-data` | Repetir el paso 6 |
| Certbot falla | El dominio no apunta a la IP o el puerto 80 está cerrado | Revisar DNS y el firewall |
