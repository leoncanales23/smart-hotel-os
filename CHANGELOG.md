# Changelog — SmartHotelOS
### Grupo Smart de Administración

Todos los cambios notables de este proyecto se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/).
Versionado semántico: [SemVer](https://semver.org/).

---

## [Unreleased]
### En progreso
- Módulo `smart_upselling` — Upselling inteligente con IA
- Módulo `smart_forecasting` — Predicción ML de ocupación
- App PWA para staff (housekeeping + concierge)
- Integración IoT cerraduras y climatización
- Multi-propiedad panel consolidado

---

## [1.0.0] — 2026-06-11 🚀

### ✨ Módulos nuevos

#### Smart Front Desk (`smart_frontdesk`)
- Check-in / check-out digital completo
- Asignación automática de habitaciones
- Captura digital de documentos de identidad
- Llaves digitales QR/NFC/PIN
- Pre check-in 24h antes de llegada
- Notas de turno entre recepcionistas
- Panel de llegadas en tiempo real (arrivals.tpl)
- Panel de salidas del día (departures controller)
- Vista de huéspedes en casa (inhouse.tpl)
- Integración WhatsApp para bienvenida

#### Smart Housekeeping (`smart_housekeeping`)
- Panel de pisos en tiempo real (board.tpl)
- Estados de habitación: 11 estados estándar
- Generación automática de plan diario
- Asignación inteligente de staff por carga
- Turndown service automatizado
- Inspecciones con checklist fotográfico
- Gestión de objetos perdidos y encontrados
- App Vue.js 3 para camareras (HousekeepingBoard.js)
- WebSocket para actualizaciones en tiempo real
- KPI de productividad por camarera

#### Smart Dashboard (`smart_dashboard`)
- KPIs en tiempo real: ocupación, RevPAR, ADR, TRevPAR
- Snapshots diarios para histórico
- Alertas operacionales automáticas con umbrales configurables
- Dashboard ejecutivo Vue.js 3 (ExecutiveDashboard.js)
- Gráficas de tendencia RevPAR + Ocupación
- Comparativa año vs año (YoY)
- Targets por propiedad
- Evaluación automática de alertas vía cron

#### Smart Guest Profile CRM (`smart_guestprofile`)
- Perfil 360° del huésped
- Historial completo de estancias
- Preferencias personales por categoría (14 categorías)
- Alergias e intolerancias con niveles de severidad
- Ocasiones especiales y recordatorios automáticos
- Segmentación automática: STANDARD / FREQUENT / VIP
- Tags de color personalizados
- Briefing de llegada para recepción
- Código único de huésped (SG-XXXXXX)
- Notas privadas del staff y concierge

#### Smart Rate Manager (`smart_ratemanager`)
- 5 tipos de planes tarifarios (BAR, Anticip., No Remb., Paquete, Corp.)
- Gestión de tarifas por fecha, canal y tipo de habitación
- Calendario visual de tarifas Vue.js 3 (RateCalendar.js)
- Motor de Yield Management automático
- Verificación de paridad de precios entre canales
- Log de auditoría de cambios de tarifa
- Actualización masiva de tarifas por período
- Sugerencias de yield con indicadores visuales

#### Smart Notifications Hub (`smart_notifications`)
- Cola de notificaciones multi-canal
- WhatsApp Business API (Meta Cloud API v18.0)
- Email vía sistema QloApps (Mail class)
- Notificaciones internas al dashboard
- Push notifications (estructura para FCM/APNs)
- Templates predefinidos: welcome, pre-checkin, spa, mantenimiento, alertas
- Log completo de envíos
- Procesador de cola via cron (50 notif/ejecución)

#### Smart Restaurant & F&B (`smart_restaurant`)
- POS completo para restaurante, bar y room service
- Gestión de mesas con estado en tiempo real
- Comandas digitales con envío a cocina
- Menú digital multiidioma con alérgenos
- Cargos directos a habitación
- Turnos de caja
- Reportes F&B diarios por outlet
- 10 ítems de menú de demo

#### Smart Spa & Wellness (`smart_spa`)
- Reservas de tratamientos con disponibilidad en tiempo real
- Gestión de terapeutas y especialidades
- Agenda diaria del spa
- 8 servicios predefinidos (masajes, facial, yoga, fitness, paquetes)
- Control de acceso a gimnasio y áreas wellness
- Horarios de disponibilidad por terapeuta
- Recordatorios automáticos 2h antes

#### Smart Maintenance (`smart_maintenance`)
- Tickets de mantenimiento correctivo y preventivo
- SLA automático según prioridad (10min urgente → 24h baja)
- Asignación a técnicos con verificación de carga
- Modo OOS automático para habitaciones con ticket urgente
- Plan de mantenimiento preventivo programado
- Control de activos del hotel
- Verificación de SLA breaches vía cron
- KPIs: tickets abiertos, SLA cumplidos, habitaciones OOS

#### Smart Concierge Digital (`smart_concierge`)
- Bot WhatsApp 24/7 para huéspedes
- Detección de intenciones por palabras clave (15 categorías)
- Respuestas automáticas multi-idioma
- Escalada inteligente a agente humano
- Gestión de solicitudes (room service, transport, spa, etc.)
- Mensaje de bienvenida automático en check-in
- Historial de conversaciones y solicitudes
- Webhook Meta Cloud API con validación de IPs

### 🏗️ Infraestructura

#### API REST Central (`smart-api/SmartApiController.php`)
- 25+ endpoints versionados en `/api/smart/v1/`
- Autenticación: Bearer Token (JWT) + API Key SHA-256
- Rate limiting: 100 req/min por API Key (Redis)
- CORS configurado para integraciones externas
- Routing con parámetros dinámicos `{id}`
- Log completo de llamadas a la API
- Health check endpoint

#### Docker Stack
- Nginx 1.25 con configuración de producción
- PHP 8.2 FPM Alpine con todas las extensiones necesarias
- MySQL 8.0 con charset utf8mb4
- Redis 7.2 para cache, sessions y rate limiting
- Node.js 20 para assets Vite/Vue.js
- PHPMyAdmin, Mailpit para desarrollo
- Docker Compose con perfiles (tools, frontend, workers)

#### CI/CD (GitHub Actions)
- Pipeline completo: lint → PHPStan → tests → security → deploy
- Deploy automático a staging en push a `develop`
- Deploy a producción vía tags semánticos
- Coverage con Codecov
- Security scan con Trivy

#### Tests
- PHPUnit 11 configurado
- 5 suites de tests unitarios (Dashboard, RateManager, Housekeeping, GuestProfile, Concierge, API)
- Stubs completos de QloApps para tests sin framework
- Bootstrap standalone para tests CI

#### Scripts de gestión
- `install-smart.php` — Instalador maestro con CLI colorido
- `seed-demo-data.php` — Datos de demostración completos
- `cron.php` — Gestor de tareas programadas (8 tasks)
- `deploy.sh` — Deploy a staging/producción con backup

### 📐 Diseño y UX

#### Tema admin premium (`smart-themes/smart-luxury/css/admin.css`)
- Sistema de diseño completo dark/gold
- 800+ líneas de CSS variables-based
- Componentes: cards, tablas, modales, formularios, badges
- Animaciones y transiciones Vue.js
- Vue transitions (slide-in, fade)
- Responsive para pantallas de staff
- Print styles para listas de llegadas
- Skeleton loaders

#### Landing page (`smart-themes/smart-luxury/landing.html`)
- Página de presentación del sistema
- Diseño de lujo: dark theme + gold
- Custom cursor animado
- Grid de módulos con hover effects
- Roadmap visual
- Métricas Forbes 5-Star
- Diagrama de arquitectura interactivo

### 📚 Documentación
- `README.md` — Visión completa del sistema
- `ARCHITECTURE.md` — 5 ADRs, modelo de datos, seguridad
- `CONTRIBUTING.md` — Guía de desarrollo y PR checklist
- `docs/api/REST-API.md` — Documentación completa de la API
- `CHANGELOG.md` — Este archivo

### 🗄️ Base de datos
**47 tablas creadas** distribuidas en 10 módulos:
- 6 tablas de Front Desk
- 6 tablas de Housekeeping
- 3 tablas de Dashboard (+ 2 compartidas: smart_room_charge, smart_api_key/log)
- 6 tablas de Guest Profile CRM
- 4 tablas de Rate Manager
- 2 tablas de Notifications
- 7 tablas de Restaurant/F&B
- 6 tablas de Spa & Wellness
- 4 tablas de Maintenance
- 3 tablas de Concierge

### 🔢 Estadísticas del release
| Métrica                  | Valor   |
|--------------------------|---------|
| Archivos totales         | 56      |
| Líneas de PHP            | ~4.200  |
| Líneas de JavaScript     | ~1.100  |
| Líneas de CSS            | ~820    |
| Líneas de SQL            | ~350    |
| Módulos propietarios     | 10      |
| Endpoints API REST       | 25+     |
| Tablas de BD             | 47      |
| Tests unitarios          | 30+     |
| Estándar objetivo        | Forbes 5-Star |

---

## [0.1.0] — 2026-06-03

### Añadido
- Creación del repositorio `smart-hotel-os`
- Arquitectura base del sistema
- Estructura de módulos propietarios Smart Group
- Docker Compose para entorno de desarrollo
- GitHub Actions básico
- README y ARCHITECTURE iniciales

---

*SmartHotelOS — Construyendo el futuro de la hospitalidad de lujo en Latinoamérica* 🌟
