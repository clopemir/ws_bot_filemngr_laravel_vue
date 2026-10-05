# Despliegue en CloudPanel con Dploy

Guía para actualizar el bot en un servidor con **CloudPanel v2**, **PHP 8.4**, **MySQL** y despliegues sin downtime con **[Dploy](https://www.cloudpanel.io/docs/v2/dploy/introduction/)**.

Archivos de referencia en el repo:

| Archivo | Destino en el servidor |
|---|---|
| `deploy/dploy/config.yml` | `~/.dploy/config.yml` (usuario `fpcorporativo-bot`) |
| `deploy/dploy/env.production.example` | `~/.dploy/overlays/.env` |
| `.github/workflows/deploy.yml` | (opcional) despliegue desde GitHub Actions |

---

## Actualizar el servidor actual (bot.fpcorporativo.com)

El sitio ya existe (usuario `fpcorporativo-bot`, PHP 8.4, MySQL, Dploy instalado). Para pasar a esta versión:

### 1. Respaldo

```bash
cd ~/htdocs/ws_bot_filemngr_laravel_vue
mysqldump -u <usuario-db> -p <base-de-datos> > ~/respaldo-$(date +%F).sql
tar czf ~/storage-$(date +%F).tgz shared/
```

### 2. Rotar las credenciales expuestas

Los valores del `.env` anterior se compartieron fuera del servidor. Genera nuevos antes de desplegar:

| Credencial | Dónde se regenera |
|---|---|
| `WHATSAPP_TOKEN` | Meta Business > Usuarios del sistema > Generar token (y revoca el anterior) |
| `WHATSAPP_VERIFY_TOKEN` | Cadena aleatoria nueva (`openssl rand -hex 24`), también en la config del webhook en Meta |
| `GEMINI_API_KEY` (las dos) | Google AI Studio / Cloud Console > Credenciales: elimina ambas y crea una nueva |
| `DB_PASSWORD` | CloudPanel > Databases > Users > cambiar contraseña |
| `APP_KEY` | `php artisan key:generate --show` (cierra las sesiones abiertas del panel) |

### 3. Actualizar `~/.dploy/config.yml` y el overlay `.env`

```bash
nano ~/.dploy/config.yml       # contenido de deploy/dploy/config.yml
nano ~/.dploy/overlays/.env    # aplica los cambios de deploy/dploy/env.production.example
chmod 600 ~/.dploy/overlays/.env
```

Cambios clave respecto a la configuración anterior:

- Se comparte `storage/app/private` (los documentos nuevos viven ahí; sin esto se pierden en cada deploy).
- Se ejecuta `migrate --force` (esta versión agrega tablas y columnas).
- Se quitó `storage:link`. `files:make-private` se ejecuta a mano después de validar (ver paso 6).
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `WHATSAPP_APP_SECRET`, SMTP real, `SESSION_DRIVER=database`, `LOG_STACK=daily`, `APP_URL` sin `/` final.

Crea el nuevo directorio compartido (revisa con `ls shared/` cómo Dploy organiza los existentes y sigue la misma estructura):

```bash
cd ~/htdocs/ws_bot_filemngr_laravel_vue
mkdir -p shared/storage/app/private && chmod 0770 shared/storage/app/private
```

### 4. Configurar el App Secret en Meta *antes* de desplegar

Copia el **App Secret** (App > Configuración > Básica) a `WHATSAPP_APP_SECRET`. Sin él, la nueva versión rechaza todos los webhooks y el bot deja de responder.

### 5. Desplegar

```bash
dploy deploy main
```

Después, elimina el symlink público antiguo si sigue existiendo en la release (`public/storage`) y confirma que `https://bot.fpcorporativo.com/storage/clientes/` responde 404.

### 6. Validar y mover los documentos a privado

Prueba el bot con un cliente real (desde su número registrado). Si todo funciona:

```bash
cd ~/htdocs/ws_bot_filemngr_laravel_vue/current
php artisan files:make-private --dry-run
php artisan files:make-private
```

### Rollback

Si algo falla **antes** del paso 6, vuelve a la release anterior (las migraciones solo agregan columnas y tablas, así que la versión anterior funciona con la base de datos nueva):

```bash
cd ~/htdocs/ws_bot_filemngr_laravel_vue
ls releases/                                   # identifica la anterior
ln -sfn releases/<release-anterior> current
sudo systemctl reload php8.4-fpm
```


## 7. Cron (colas y tareas programadas)

En **CloudPanel → Sites → Cron Jobs**, una sola entrada cada minuto:

```
* * * * * /usr/bin/php8.4 /home/fpcorporativo-bot/htdocs/ws_bot_filemngr_laravel_vue/current/artisan schedule:run >> /dev/null 2>&1
```

Esto procesa la cola (envío de documentos, avisos a agentes) y limpia códigos de verificación antiguos. No se necesita supervisor.

## 8. Configuración en Meta (WhatsApp Cloud API)

1. **Webhook**: URL `https://bot.fpcorporativo.com/webhook`, token = `WHATSAPP_VERIFY_TOKEN`; suscribe el campo `messages`.
2. **App Secret** (App → Configuración → Básica) → `WHATSAPP_APP_SECRET`. **Obligatorio**: sin él se rechazan todos los webhooks.
3. **Token permanente** de un usuario de sistema → `WHATSAPP_TOKEN`.
4. *(Recomendado)* Crea una plantilla de categoría **Autenticación** con botón "Copiar código", en español (`es_MX`), y pon su nombre en `WHATSAPP_OTP_TEMPLATE`. Así el código de verificación también llega al WhatsApp registrado del titular.

Después de cambiar el `.env` en el servidor: `php8.4 artisan optimize` (o vuelve a desplegar).

## 9. Despliegue desde GitHub (opcional)

Agrega los secrets `DEPLOY_HOST`, `DEPLOY_USER` y `DEPLOY_SSH_KEY` y ejecuta **Actions → deploy → Run workflow**.

## 10. Checklist posterior

- [ ] `https://bot.fpcorporativo.com/up` responde 200.
- [ ] `https://bot.fpcorporativo.com/log-viewer` pide inicio de sesión.
- [ ] `https://bot.fpcorporativo.com/storage/clientes/...` responde 404.
- [ ] Un mensaje desde el teléfono registrado de un cliente de prueba recibe sus documentos.
- [ ] El mismo RFC desde otro número pide código y el agente recibe la alerta.
- [ ] `storage/logs/security-*.log` registra las verificaciones.

---

# Modelo de seguridad del bot

| Capa | Qué protege |
|---|---|
| Firma `X-Hub-Signature-256` | Solo Meta puede enviar mensajes al webhook; nadie puede suplantar un número. |
| **Factor 1: número registrado** | El RFC solo da acceso si el WhatsApp que escribe es el `client_phone` del cliente. |
| **Factor 2: código de un solo uso** | Si el RFC llega desde otro número, se envía un código de 6 dígitos **al titular** (correo y/o WhatsApp registrado). El solicitante necesita que el titular se lo comparta. 3 intentos, 10 min de vigencia, máx. 3 códigos por cliente al día. |
| Respuesta uniforme | Desde un número no registrado, el bot responde igual exista o no el RFC: no sirve para averiguar quién es cliente. |
| Límite de intentos | 5 RFC por número por hora. |
| Sesión con caducidad | 15 min; "menú" la cierra. El cliente nunca se toma del mensaje, siempre de la sesión. |
| IDs opacos | Los botones ya no llevan el RFC; las opciones escritas como texto se ignoran. |
| Documentos privados | Disco privado + enlaces firmados que vencen en 10 min. |
| Alertas al agente | Intento desde número no registrado, acceso por código y bloqueo. |
| Bitácora | `storage/logs/security-*.log` (90 días), con datos personales enmascarados. |
| Agentes | Solo pueden pedir documentos de sus propios clientes. |

> **Nota sobre las alertas:** WhatsApp solo entrega texto libre a números que escribieron al bot en las últimas 24 h. Para garantizar que las alertas lleguen siempre a los agentes, conviene migrarlas a plantillas aprobadas.
