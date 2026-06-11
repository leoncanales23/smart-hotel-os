# Guía de Contribución — SmartHotelOS
### Grupo Smart de Administración

---

## Ramas y flujo de trabajo

```
main          ← producción (solo via PR con aprobación)
develop       ← integración continua
feature/XXX   ← nuevas funcionalidades
fix/XXX       ← correcciones de bugs
hotfix/XXX    ← correcciones urgentes en producción
```

**Flujo normal:**
1. Crear rama desde `develop`: `git checkout -b feature/smart-upselling`
2. Desarrollar + tests
3. PR a `develop` → revisión de código
4. Merge a `develop` → deploy automático a staging
5. Release → merge de `develop` a `main` con tag semántico

---

## Convención de commits (Conventional Commits)

```
feat(frontdesk): add digital key generation via NFC
fix(housekeeping): prevent duplicate task assignment on checkout
docs(api): update availability endpoint documentation
test(ratemanager): add yield engine edge case tests
refactor(dashboard): extract KPI calculation to service class
chore(docker): upgrade PHP image to 8.2.18
```

**Tipos:** `feat` `fix` `docs` `test` `refactor` `perf` `chore` `style` `ci`  
**Scopes:** `frontdesk` `housekeeping` `dashboard` `guestprofile` `ratemanager` `restaurant` `spa` `maintenance` `concierge` `notifications` `api` `docker` `ci`

---

## Convenciones de código PHP

### Estándar: PSR-12
```php
<?php
// ── Correcto ──────────────────────────────────────────────────

class Smart_Frontdesk extends Module
{
    const MODULE_VERSION = '1.0.0';

    public function processCheckIn(
        int   $idBooking,
        int   $idRoom,
        array $guestData,
        int   $idAgent
    ): array {
        if (!$idBooking || !$idRoom) {
            return ['success' => false, 'message' => 'Datos inválidos'];
        }
        // ...
    }
}
```

### Reglas específicas de SmartHotelOS:
- **Nunca** usar `$_POST`, `$_GET` directamente → usar `Tools::getValue()`
- **Nunca** SQL sin `pSQL()` para strings → prevención de injection
- **Siempre** `(int)` para IDs: `$idRoom = (int) $data['id_room']`
- **Siempre** log errores críticos: `PrestaShopLogger::addLog(..., 3)`
- **Siempre** retornar `['success' => bool, 'message' => string]` en métodos de acción
- **Siempre** verificar que el módulo dependiente esté instalado antes de usarlo

---

## Estructura de un módulo Smart

```
smart_[nombre]/
├── smart_[nombre].php          # Clase principal (install/uninstall/hooks)
├── controllers/
│   ├── admin/                  # AdminController heredando ModuleAdminController
│   └── front/                  # FrontController para rutas públicas
├── views/
│   ├── templates/admin/*.tpl   # Plantillas Smarty/Twig del back-office
│   ├── templates/front/*.tpl   # Plantillas del front-end
│   ├── templates/hook/*.tpl    # Plantillas para hooks
│   └── js/*.js                 # Vue.js 3 components
├── classes/                    # Entidades y servicios de negocio
├── sql/
│   ├── install.php             # CREATE TABLE ... (retorna bool)
│   └── uninstall.php           # DROP TABLE IF EXISTS ...
├── api/                        # Handlers de la API REST del módulo
├── tests/                      # Tests específicos del módulo
└── README.md                   # Documentación del módulo
```

---

## Tests

```bash
# Ejecutar todos los tests
vendor/bin/phpunit

# Solo tests unitarios
vendor/bin/phpunit tests/unit/

# Con coverage HTML
vendor/bin/phpunit --coverage-html coverage/

# Un test específico
vendor/bin/phpunit tests/unit/SmartHotelOSTest.php::SmartRateManagerTest
```

**Cobertura mínima requerida:** 70% en módulos core (frontdesk, housekeeping, dashboard).

---

## Análisis estático

```bash
# PHPStan nivel 5
vendor/bin/phpstan analyse smart-modules/ smart-api/ --level=5

# PHP CodeSniffer PSR-12
vendor/bin/phpcs --standard=PSR12 smart-modules/

# Auto-fix estilo
vendor/bin/php-cs-fixer fix smart-modules/
```

---

## Guía de módulo nuevo

1. Crear estructura con el script:
   ```bash
   php scripts/create-module.php smart_upselling "Upselling inteligente"
   ```

2. La clase principal debe:
   - Extender `Module`
   - Implementar `install()` y `uninstall()` completos
   - Tener `installSql()` con CREATE TABLE IF NOT EXISTS
   - Registrar hooks relevantes
   - Definir constantes de configuración

3. Añadir al array `$modules` en `scripts/install-smart.php`

4. Añadir tests en `tests/unit/` y `tests/integration/`

5. Documentar en `docs/modules/smart_upselling.md`

---

## Revisión de código (PR checklist)

- [ ] Tests pasan (`vendor/bin/phpunit`)
- [ ] PHPStan sin errores nuevos de nivel 5
- [ ] PHPCS sin violaciones PSR-12
- [ ] `install()` y `uninstall()` completos y testeados
- [ ] SQL usa `IF NOT EXISTS` (idempotente)
- [ ] Todos los inputs pasados por `pSQL()` / `(int)` / `(float)`
- [ ] Logs de errores en bloques `catch`
- [ ] CHANGELOG.md actualizado
- [ ] Sin credenciales hardcodeadas
- [ ] Sin `var_dump` / `die` en código de producción

---

*SmartHotelOS — Construyendo el futuro de la hospitalidad de lujo.*
