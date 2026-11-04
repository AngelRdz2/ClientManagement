# Despliegue — CobraCRM (ClientManagement)

Servidor de pruebas compartido con **retazo** y **autocore**. Aquí no hay contraseñas: están solo en el servidor.

| | |
|---|---|
| URL | **http://138.197.97.254** (puerto 80) |
| Servidor | DigitalOcean · Ubuntu 24.04 · 1 vCPU · 512 MB RAM + 2 GB swap |
| IP / SSH | `138.197.97.254` · usuario `root`, acceso con llave SSH |
| Stack | Nginx 1.24 · PHP 8.4-FPM · MySQL 8.0 · Composer · Supervisor · cron · firewall UFW |
| Código | `/var/www/crm` (clonado de `github.com/AngelRdz2/ClientManagement`, rama `master`) |
| Base de datos | MySQL `client_management`, usuario `client_management` (contraseña en el `.env` del servidor) |

Otros sistemas en el mismo servidor: retazo → `http://138.197.97.254:8081`, autocore → `http://138.197.97.254:8082`.

## 1. Conectarse al servidor

El acceso es con llave SSH (no se comparten contraseñas).

1. Crea tu llave, si no tienes una (en PowerShell o Terminal):
   ```bash
   ssh-keygen -t ed25519
   ```
2. Envía al administrador del servidor el contenido de tu llave **pública** (`~/.ssh/id_ed25519.pub`; nunca el archivo sin `.pub`).
3. El administrador la agrega en el servidor:
   ```bash
   echo "ssh-ed25519 AAAA... tu@equipo" >> /root/.ssh/authorized_keys
   ```
4. Conéctate:
   ```bash
   ssh root@138.197.97.254
   ```

## 2. Dónde está cada cosa

| Qué | Dónde |
|---|---|
| Código | `/var/www/crm` (dueño `www-data`) |
| Configuración | `/var/www/crm/.env` (`APP_ENV=production`, `APP_DEBUG=false`) |
| Assets compilados | `/var/www/crm/public/build` (se compilan en tu PC, no en el servidor) |
| Log de Laravel | `/var/www/crm/storage/logs/laravel.log` |
| Log de la cola | `/var/www/crm/storage/logs/queue.log` |
| Nginx | `/etc/nginx/sites-available/crm` · logs en `/var/log/nginx/` |
| Cola (Supervisor) | programa `crm-queue`: `queue:work --queue=campaigns,whatsapp,default` · `/etc/supervisor/conf.d/crm-queue.conf` |
| Programador (cron) | `/etc/cron.d/laravel-crm` → `schedule:run` cada minuto |
| Usuario administrador | `/root/credenciales.txt` (solo lo lee root) |
| Script de despliegue | `/root/deploy/deploy-app.sh` |

## 3. Actualizar el sistema

El servidor tiene solo 512 MB de RAM, así que **el frontend se compila en tu PC** y se sube ya compilado.

1. Sube tus cambios a GitHub (`master`):
   ```bash
   git push origin master
   ```
2. Compila el frontend y empaquétalo (desde la carpeta del proyecto):
   ```bash
   npm run build
   ```
   ```bash
   tar -czf crm-build.tar.gz public/build
   ```
3. Súbelo al servidor:
   ```bash
   scp crm-build.tar.gz root@138.197.97.254:/root/deploy/
   ```
4. Despliega (hace `git pull`, instala dependencias, migra, optimiza y recarga Nginx, PHP y la cola):
   ```bash
   ssh root@138.197.97.254 "rm -rf /var/www/crm/public/build && bash /root/deploy/deploy-app.sh crm"
   ```

Las migraciones se ejecutan con `--force`; revisa que sean seguras antes de desplegar.

## 4. Comandos útiles (en el servidor)

Los comandos de Artisan se ejecutan como `www-data` para no dejar archivos de root:

```bash
cd /var/www/crm && sudo -u www-data php artisan about
```
```bash
tail -f /var/www/crm/storage/logs/laravel.log
```
```bash
supervisorctl status
```
```bash
supervisorctl restart crm-queue
```
```bash
cd /var/www/crm && sudo -u www-data php artisan optimize && systemctl reload php8.4-fpm
```
```bash
mysql client_management
```

OPcache no revisa cambios en archivos (`opcache.validate_timestamps=0`): si editas algo a mano en el servidor, recarga PHP con `systemctl reload php8.4-fpm`.

## 5. Notas

- **Sin HTTPS** mientras no haya dominio: los webhooks de WhatsApp (Meta/Twilio) no funcionan. Con un dominio apuntando a la IP se activa con Certbot (ya instalado).
- WhatsApp está en **modo simulación** y el correo en `MAIL_MAILER=log` (no se envían correos).
- Solo existe el usuario administrador; no se cargaron los usuarios demo (`*@crm.test`).
- Las tres apps comparten la IP, así que cada una usa su propia cookie de sesión (`SESSION_COOKIE=crm_session`).
