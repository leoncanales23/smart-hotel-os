# 🏨 SmartHotelOS — Sistema Operativo de Gestión Hotelera
### Grupo Smart de Administración · Estándar 5 Estrellas

> **Repositorio:** `smart-hotel-os`
> **Versión:** 1.0.0-alpha
> **Base tecnológica:** QloApps (OSL-3.0) + Módulos propietarios Smart Group
> **Estándar objetivo:** Forbes 5-Star · Leading Hotels of the World

---

## 📋 Tabla de Contenidos

- [Visión del Sistema](#visión-del-sistema)
- [Arquitectura General](#arquitectura-general)
- [Módulos del Sistema](#módulos-del-sistema)
- [Stack Tecnológico](#stack-tecnológico)
- [Estructura del Repositorio](#estructura-del-repositorio)
- [Instalación y Configuración](#instalación-y-configuración)
- [Roadmap de Desarrollo](#roadmap-de-desarrollo)
- [Estándares de Calidad](#estándares-de-calidad)
- [Equipo y Contribución](#equipo-y-contribución)

---

## 🎯 Visión del Sistema

**SmartHotelOS** es el sistema operativo central del Grupo Smart de Administración Hotelera. Construido sobre la base de QloApps y extendido con módulos de clase mundial, cubre la operación completa de un hotel de 5 estrellas: desde la reserva en línea hasta el cierre contable, pasando por la experiencia del huésped, operaciones de housekeeping, F&B, spa, mantenimiento y analítica ejecutiva en tiempo real.

### Principios de diseño
- **Guest-first**: Cada proceso está diseñado desde la perspectiva del huésped
- **Zero paper**: Operación 100% digitalizada
- **Real-time**: Información actualizada al segundo en todos los departamentos
- **Integración total**: Un solo sistema de verdad para todo el hotel
- **Escalable**: Preparado para gestionar desde 1 propiedad hasta 50+

---

## 🏗️ Arquitectura General

```
┌─────────────────────────────────────────────────────────────────┐
│                     SmartHotelOS v1.0                           │
│                   Grupo Smart de Administración                 │
├──────────────┬──────────────┬──────────────┬────────────────────┤
│   FRONT-END  │   BACK-OFFICE│   OPERACIONES│   INTELIGENCIA     │
│   HUÉSPED    │   GESTIÓN    │   HOTEL      │   DE NEGOCIO       │
├──────────────┼──────────────┼──────────────┼────────────────────┤
│ • Web Booking│ • PMS Central│ • Housekeep. │ • Dashboard Ejec.  │
│ • App Móvil  │ • Reservas   │ • Mant. prev.│ • RevPAR / ADR     │
│ • Kiosko     │ • Front Desk │ • F&B / Rest.│ • Forecast IA      │
│ • WhatsApp   │ • Revenue Mg.│ • Spa & Gym  │ • Channel Mgmt     │
│ • Concierge  │ • Finanzas   │ • Eventos    │ • Reportes custom  │
└──────────────┴──────────────┴──────────────┴────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
         ┌────▼────┐   ┌──────▼────┐  ┌──────▼─────┐
         │  Core   │   │   APIs    │  │  DB Layer  │
         │ QloApps │   │ REST/GraphQL  │  MySQL 8+  │
         └─────────┘   └───────────┘  └────────────┘
```

---

## 📦 Módulos del Sistema

### 🔵 CORE — Base QloApps (heredado y extendido)
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `hotelreservationsystem` | Motor central de reservas y habitaciones | ✅ Base lista |
| `wkhotelroom` | Gestión de tipos de habitación y tarifas | ✅ Base lista |
| `wkroomsearchblock` | Motor de búsqueda de disponibilidad | ✅ Base lista |
| `qlochannelmanagerconnector` | Conexión a OTAs (Booking, Expedia, etc.) | 🔧 Extender |
| `qlohotelreview` | Sistema de reseñas y NPS | 🔧 Extender |

### 🟡 SMART PMS — Property Management System Premium
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_frontdesk` | Check-in / Check-out digital + kiosko | 🚧 Desarrollar |
| `smart_housekeeping` | Gestión de pisos, asignación y control | 🚧 Desarrollar |
| `smart_maintenance` | Mantenimiento preventivo y correctivo | 🚧 Desarrollar |
| `smart_guestprofile` | Perfil 360° del huésped, preferencias CRM | 🚧 Desarrollar |
| `smart_concierge` | Servicios de conserjería y peticiones | 🚧 Desarrollar |

### 🟠 SMART REVENUE — Gestión de Ingresos
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_ratemanager` | Gestión dinámica de tarifas | 🚧 Desarrollar |
| `smart_yielding` | Yield management automático con IA | 🚧 Desarrollar |
| `smart_overbooking` | Control y gestión de overbooking | 🚧 Desarrollar |
| `smart_packages` | Paquetes especiales y upselling | 🚧 Desarrollar |

### 🟢 SMART F&B — Alimentos y Bebidas
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_restaurant` | POS restaurante + gestión de mesas | 🚧 Desarrollar |
| `smart_roomservice` | Servicio a la habitación 24h | 🚧 Desarrollar |
| `smart_minibar` | Control de minibar automatizado | 🚧 Desarrollar |
| `smart_banquets` | Eventos y banquetes | 🚧 Desarrollar |

### 🔴 SMART SPA & WELLNESS
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_spa` | Reservas y gestión de spa/wellness | 🚧 Desarrollar |
| `smart_gym` | Acceso y control de gimnasio | 🚧 Desarrollar |
| `smart_activities` | Actividades y tours | 🚧 Desarrollar |

### 🟣 SMART ANALYTICS — Inteligencia de Negocio
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_dashboard` | Dashboard ejecutivo en tiempo real | 🚧 Desarrollar |
| `smart_forecasting` | Predicción de ocupación con ML | 🚧 Desarrollar |
| `smart_reports` | Reportería avanzada multi-propiedad | 🚧 Desarrollar |
| `smart_kpis` | KPIs de estándar Forbes 5 estrellas | 🚧 Desarrollar |

### ⚪ SMART INTEGRATIONS — Conectores Externos
| Módulo | Descripción | Estado |
|--------|-------------|--------|
| `smart_pms_api` | API REST central para integraciones | 🚧 Desarrollar |
| `smart_payment_gateway` | Pasarelas de pago premium (Stripe, etc.) | 🚧 Desarrollar |
| `smart_whatsapp` | Bot WhatsApp para huéspedes | 🚧 Desarrollar |
| `smart_iot` | Integración con IoT (cerraduras, A/C, luces) | 🚧 Desarrollar |
| `smart_keycard` | Llaves digitales y móviles | 🚧 Desarrollar |

---

## 🛠️ Stack Tecnológico

### Backend
- **PHP 8.2+** con QloApps framework
- **MySQL 8.0+** · Redis para cache
- **API REST** + WebSockets para tiempo real
- **Queue system** (Laravel Queues / RabbitMQ)

### Frontend
- **Twig** templates (admin) · **Vue.js 3** (interfaces operativas)
- **Tailwind CSS** · **Alpine.js** para interactividad
- **PWA** para app operacional de staff

### Infraestructura
- **Docker** + **Docker Compose** para desarrollo
- **Nginx** como servidor web
- **CI/CD** via GitHub Actions
- **Entornos:** Development · Staging · Production

### Integraciones previstas
- **Stripe / MercadoPago** — pagos
- **Twilio / WhatsApp Business API** — comunicaciones
- **Google Analytics 4** — analítica web
- **Mailchimp / SendGrid** — marketing
- **OTAs:** Booking.com, Expedia, Airbnb via channel manager

---

## 📁 Estructura del Repositorio

```
smart-hotel-os/
├── 📄 README.md                    # Este archivo
├── 📄 ARCHITECTURE.md              # Decisiones de arquitectura
├── 📄 CONTRIBUTING.md              # Guía de contribución
├── 📄 CHANGELOG.md                 # Historial de cambios
├── 📄 .env.example                 # Variables de entorno plantilla
├── 🐳 docker-compose.yml           # Stack de desarrollo completo
├── 🐳 Dockerfile                   # Imagen de producción
│
├── 📁 core/                        # QloApps base (subtree)
│   └── ...                         # Código fuente QloApps
│
├── 📁 smart-modules/               # Módulos propietarios Smart Group
│   ├── smart_frontdesk/
│   ├── smart_housekeeping/
│   ├── smart_dashboard/
│   ├── smart_guestprofile/
│   ├── smart_restaurant/
│   ├── smart_spa/
│   ├── smart_ratemanager/
│   ├── smart_pms_api/
│   └── ...
│
├── 📁 smart-themes/                # Tema premium 5 estrellas
│   ├── smart-luxury/               # Tema principal
│   └── smart-luxury-mobile/        # Versión móvil
│
├── 📁 smart-api/                   # API REST standalone
│   ├── routes/
│   ├── controllers/
│   └── middleware/
│
├── 📁 docs/                        # Documentación técnica
│   ├── modules/
│   ├── api/
│   ├── deployment/
│   └── standards/
│
├── 📁 tests/                       # Suite de pruebas
│   ├── unit/
│   ├── integration/
│   └── e2e/
│
├── 📁 scripts/                     # Scripts de deployment y utilidades
│   ├── install.sh
│   ├── deploy.sh
│   └── seed-demo-data.sh
│
└── 📁 .github/                     # GitHub Actions workflows
    └── workflows/
        ├── ci.yml
        ├── staging-deploy.yml
        └── production-deploy.yml
```

---

## 🚀 Instalación y Configuración

### Requisitos previos
- Docker Desktop 4.x+
- Git 2.x+
- Node.js 18+ (para assets)
- Composer 2.x+

### Inicio rápido (desarrollo)
```bash
# 1. Clonar el repositorio
git clone https://github.com/smart-group/smart-hotel-os.git
cd smart-hotel-os

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Levantar el stack
docker-compose up -d

# 4. Instalar dependencias
docker-compose exec app composer install
docker-compose exec app npm install && npm run dev

# 5. Configurar la base de datos
docker-compose exec app php scripts/install.php

# Acceder a:
# Frontend: http://localhost:8080
# Admin:    http://localhost:8080/admin-smart
# PHPMyAdmin: http://localhost:8081
```

---

## 📅 Roadmap de Desarrollo

### Fase 1 — MVP Core (Semanas 1-6)
- [ ] Setup infraestructura Docker
- [ ] Integración y limpieza de base QloApps
- [ ] Módulo `smart_frontdesk` — Check-in/out digital
- [ ] Módulo `smart_housekeeping` — Gestión de pisos
- [ ] Módulo `smart_dashboard` — KPIs básicos
- [ ] Tema premium inicial

### Fase 2 — Revenue & Guest (Semanas 7-12)
- [ ] Módulo `smart_guestprofile` — CRM de huéspedes
- [ ] Módulo `smart_ratemanager` — Gestión de tarifas
- [ ] Módulo `smart_concierge` — Servicios digitales
- [ ] Integración WhatsApp
- [ ] App PWA para staff

### Fase 3 — F&B & Spa (Semanas 13-18)
- [ ] Módulo `smart_restaurant` — POS completo
- [ ] Módulo `smart_spa` — Reservas wellness
- [ ] Módulo `smart_roomservice` — Servicio en habitación
- [ ] Módulo `smart_banquets` — Eventos

### Fase 4 — Inteligencia & Scale (Semanas 19-24)
- [ ] Módulo `smart_forecasting` — IA predictiva
- [ ] Módulo `smart_iot` — Integración IoT
- [ ] Multi-propiedad
- [ ] Módulo `smart_reports` — Reportería ejecutiva

---

## ⭐ Estándares de Calidad

Este sistema es diseñado para cumplir y superar los estándares de:

- **Forbes Travel Guide 5-Star** — Criterios de servicio y tecnología
- **Leading Hotels of the World** — Estándares de lujo y experiencia
- **PCI DSS Level 1** — Seguridad en pagos
- **GDPR / Ley de Datos Chile** — Protección de datos de huéspedes
- **ISO 27001** — Seguridad de la información

### KPIs objetivo del sistema
| KPI | Objetivo |
|-----|----------|
| Uptime del sistema | 99.95% |
| Tiempo de respuesta API | < 200ms (p95) |
| Check-in digital | < 3 minutos |
| NPS tecnología | > 72 |
| Resolución de incidencias | < 15 minutos |

---

## 👥 Equipo y Contribución

**Grupo Smart de Administración**
- Dirección de Tecnología
- Dirección de Operaciones Hoteleras
- Equipo de Desarrollo

Para contribuir al proyecto, ver [CONTRIBUTING.md](./CONTRIBUTING.md).

---

## 📄 Licencia

- Código base QloApps: **OSL-3.0**
- Módulos propietarios Smart Group: **Propietario — Todos los derechos reservados**

---

*SmartHotelOS — Construyendo el futuro de la hospitalidad de lujo en Latinoamérica* 🌟
