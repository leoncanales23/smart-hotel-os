# Arquitectura Técnica — SmartHotelOS
### Grupo Smart de Administración · Documento vivo v1.0

---

## 1. Decisiones de Arquitectura (ADR)

### ADR-001: Base tecnológica QloApps
**Decisión:** Usar QloApps como motor de reservas y PMS base, extendiendo con módulos propietarios.

**Justificación:**
- QloApps tiene 8+ años de desarrollo especializado en hotelería
- Sistema de módulos robusto y extensible
- Comunidad activa y soporte continuo
- Motor de reservas probado en miles de hoteles

**Consecuencias:**
- Seguimos el patrón de módulos de QloApps/PrestaShop
- PHP 8.2+ como lenguaje principal de backend
- MySQL como base de datos principal

---

### ADR-002: Arquitectura modular por departamento
**Decisión:** Cada departamento del hotel = un módulo independiente con su propia lógica.

**Estructura de un módulo Smart:**
```
smart_[modulo]/
├── smart_[modulo].php          # Clase principal del módulo
├── controllers/
│   ├── admin/                  # Controladores del back-office
│   └── front/                  # Controladores del front
├── views/
│   ├── templates/
│   │   ├── admin/
│   │   └── front/
│   └── js/
├── classes/                    # Entidades y lógica de negocio
├── sql/
│   ├── install.php             # SQL de instalación
│   └── uninstall.php          # SQL de desinstalación
├── api/                        # Endpoints REST del módulo
├── tests/                      # Tests del módulo
└── README.md
```

---

### ADR-003: API REST para integraciones
**Decisión:** Exponer una API REST completa bajo `/api/smart/v1/`.

**Endpoints principales:**
```
GET  /api/smart/v1/rooms/availability
POST /api/smart/v1/reservations
GET  /api/smart/v1/reservations/{id}
PUT  /api/smart/v1/reservations/{id}
GET  /api/smart/v1/guests/{id}/profile
POST /api/smart/v1/housekeeping/tasks
GET  /api/smart/v1/dashboard/kpis
```

**Autenticación:** Bearer Token (JWT) + API Key para integraciones B2B.

---

### ADR-004: Frontend reactivo para operaciones
**Decisión:** Vue.js 3 + Tailwind CSS para las interfaces operacionales del staff.

**Justificación:** Las pantallas de housekeeping, front desk y restaurante necesitan actualizaciones en tiempo real (WebSockets) que las plantillas Smarty/Twig tradicionales no proveen eficientemente.

---

### ADR-005: Multi-propiedad desde la base
**Decisión:** Diseñar todas las entidades con soporte multi-propiedad desde el inicio.

**Tabla de propiedades:**
```sql
CREATE TABLE smart_property (
    id_property INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL UNIQUE,
    address TEXT,
    stars TINYINT DEFAULT 5,
    timezone VARCHAR(50),
    currency_code VARCHAR(3),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 2. Diagrama de Capas

```
┌────────────────────────────────────────────────────────────────┐
│                      CAPA DE PRESENTACIÓN                       │
│  Web Booking  │  Admin Panel  │  Staff App  │  Kiosko  │ WAB  │
└───────────────────────────┬────────────────────────────────────┘
                            │ HTTP/WebSocket
┌───────────────────────────▼────────────────────────────────────┐
│                      CAPA DE APLICACIÓN                         │
│  Controllers  │  Hooks System  │  Module Manager  │  API REST  │
└───────────────────────────┬────────────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────────────┐
│                      CAPA DE NEGOCIO                            │
│  Reservations  │  Rates  │  Guests  │  Operations  │  Reports  │
└───────────────────────────┬────────────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────────────┐
│                      CAPA DE DATOS                              │
│  MySQL 8.0  │  Redis Cache  │  File Storage  │  Queue          │
└────────────────────────────────────────────────────────────────┘
```

---

## 3. Modelo de Datos Principal

### Entidades Core

```sql
-- Propiedades (multi-hotel)
smart_property
smart_property_lang

-- Habitaciones (extensión de QloApps)
htl_room_type (QloApps base)
smart_room_type_features
smart_room_amenities

-- Reservas (extensión de QloApps)
htl_booking (QloApps base)
smart_booking_extras
smart_booking_preferences
smart_booking_timeline

-- Perfil del Huésped (CRM)
smart_guest_profile
smart_guest_preferences
smart_guest_history
smart_guest_tags

-- Housekeeping
smart_hk_task
smart_hk_assignment
smart_hk_report
smart_hk_lost_found

-- Tarifas y Revenue
smart_rate_plan
smart_rate_restriction
smart_channel_rate

-- F&B
smart_restaurant_table
smart_restaurant_order
smart_restaurant_item
smart_minibar_consumption

-- Spa
smart_spa_service
smart_spa_booking
smart_spa_therapist
```

---

## 4. Seguridad

### Capas de seguridad implementadas
1. **Autenticación:** Session tokens + CSRF protection (QloApps base)
2. **Autorización:** RBAC por departamento y rol
3. **API:** JWT + rate limiting (100 req/min por key)
4. **Base de datos:** Prepared statements (PDO), zero raw queries
5. **Pagos:** Tokenización, nunca almacenar datos de tarjeta
6. **Auditoría:** Log de toda acción administrativa con usuario + timestamp
7. **Datos de huéspedes:** Cifrado AES-256 para PII sensible

### Roles del sistema
```
ROLE_SUPER_ADMIN        → Acceso total multi-propiedad
ROLE_PROPERTY_MANAGER   → Acceso total a una propiedad
ROLE_FRONT_DESK         → Check-in/out, reservas, caja
ROLE_REVENUE_MANAGER    → Tarifas, canales, reportes
ROLE_HOUSEKEEPING_SUP   → Asignación y supervisión pisos
ROLE_HOUSEKEEPING       → Solo ver/actualizar sus tareas
ROLE_RESTAURANT_MANAGER → F&B completo
ROLE_WAITER             → Solo su turno/mesa
ROLE_SPA_MANAGER        → Gestión spa completa
ROLE_MAINTENANCE        → Ver/actualizar tareas de mantenimiento
ROLE_READONLY           → Solo reportes
```

---

## 5. Performance

### Estrategia de cache
- **Redis L1:** Disponibilidad de habitaciones (TTL: 30s)
- **Redis L2:** Tarifas y restricciones (TTL: 5min)
- **Redis L3:** Perfiles de huéspedes (TTL: 1h)
- **DB Query cache:** Para reportes pesados
- **CDN:** Assets estáticos (imágenes, CSS, JS)

### Objetivos de performance
- Dashboard ejecutivo: < 1s carga completa
- Búsqueda de disponibilidad: < 300ms
- Check-in digital: < 5s proceso completo
- API endpoints: p95 < 200ms

---

*Documento mantenido por el equipo de arquitectura del Grupo Smart.*
