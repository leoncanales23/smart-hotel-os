# SmartHotelOS — Documentación API REST
### Grupo Smart de Administración · v1.0

**Base URL:** `https://tu-hotel.com/api/smart/v1`  
**Autenticación:** `X-API-Key: <tu-api-key>` o `Authorization: Bearer <token>`  
**Formato:** JSON — `Content-Type: application/json`

---

## Autenticación

Todas las peticiones requieren un API Key válido. Los keys se gestionan desde
el panel admin en **Configuración → API Keys**.

```http
GET /api/smart/v1/health
X-API-Key: dev_smarthotel_abc123
```

---

## Endpoints

### 🏥 Sistema

#### `GET /health`
Verifica el estado de todos los subsistemas.

**Respuesta 200:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "v1",
    "timestamp": "2026-06-10T14:30:00-04:00",
    "subsystems": {
      "database": "ok",
      "redis":    "ok",
      "modules":  {
        "smart_frontdesk":    "installed",
        "smart_housekeeping": "installed",
        "smart_dashboard":    "installed"
      }
    }
  }
}
```

---

### 🛏 Disponibilidad

#### `GET /rooms/availability`
Obtiene habitaciones disponibles con tarifas en tiempo real.

**Parámetros query:**

| Parámetro    | Tipo   | Req | Descripción                       |
|--------------|--------|-----|-----------------------------------|
| `check_in`   | date   | ✓   | Fecha entrada (YYYY-MM-DD)        |
| `check_out`  | date   | ✓   | Fecha salida (YYYY-MM-DD)         |
| `adults`     | int    |     | Adultos (default: 2)              |
| `children`   | int    |     | Niños (default: 0)                |
| `channel`    | string |     | Canal (default: DIRECT_WEB)       |
| `id_property`| int    |     | Propiedad (default: 1)            |

**Ejemplo:**
```http
GET /rooms/availability?check_in=2026-07-15&check_out=2026-07-18&adults=2
```

**Respuesta 200:**
```json
{
  "success": true,
  "data": {
    "check_in":  "2026-07-15",
    "check_out": "2026-07-18",
    "nights":    3,
    "adults":    2,
    "results": [
      {
        "id_room_type": 1,
        "name":         "Suite Deluxe",
        "available":    3,
        "max_occupancy":2,
        "rate": {
          "plan_name":      "Best Available Rate",
          "meal_plan":      "BB",
          "is_refundable":  true,
          "price_per_night":95000,
          "total_price":    285000,
          "currency":       "CLP",
          "all_rates": [
            { "plan_name": "BAR",        "total_price": 285000, "is_refundable": true  },
            { "plan_name": "Anticipada", "total_price": 256500, "is_refundable": true  },
            { "plan_name": "No Remb.",   "total_price": 242250, "is_refundable": false }
          ]
        }
      }
    ]
  }
}
```

---

### 📋 Reservas

#### `GET /reservations`
Lista reservas con filtros opcionales.

**Parámetros:** `status`, `date_from`, `date_to`, `id_customer`, `page`, `limit`

#### `GET /reservations/{id}`
Obtiene una reserva por ID.

#### `POST /reservations/{id}/checkin`
Realiza el check-in de una reserva.

**Body:**
```json
{
  "id_room":   42,
  "id_type":   "RUT",
  "id_number": "12.345.678-9",
  "id_country":"CL"
}
```

#### `POST /reservations/{id}/checkout`
Realiza el check-out. Verifica cargos pendientes automáticamente.

---

### 👤 Huéspedes

#### `GET /guests/{id}/profile`
Perfil completo del huésped con preferencias, alergias y ocasiones.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "profile": {
      "id":          123,
      "guest_code":  "SG-000123",
      "firstname":   "Carlos",
      "lastname":    "Vidal",
      "segment":     "VIP",
      "vip_level":   2,
      "total_stays": 12,
      "total_nights":38,
      "total_spent": 4560000
    },
    "preferences": [
      { "category": "ROOM",   "preference_key": "floor_preference", "preference_val": "high" },
      { "category": "PILLOW", "preference_key": "pillow_type",      "preference_val": "firm" }
    ],
    "allergies": [
      { "allergy_type": "gluten", "severity": "intolerance" }
    ],
    "occasions": [
      { "occasion_type": "BIRTHDAY", "occasion_date": "1985-06-15" }
    ],
    "tags": [
      { "tag": "VIP-2", "color": "#C9A84C" }
    ]
  }
}
```

#### `GET /guests/{id}/briefing`
Briefing de llegada para el equipo de recepción. Incluye notas del staff.

#### `POST /guests/{id}/preferences`
Actualiza una preferencia del huésped.

```json
{ "category": "ROOM", "key": "floor_preference", "value": "high", "source": "staff" }
```

---

### 🛏 Housekeeping

#### `GET /housekeeping/board`
Estado de todas las habitaciones en tiempo real.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "rooms": [
      {
        "id_room":     42,
        "room_number": "401",
        "floor":       4,
        "status":      "VD",
        "updated_at":  "2026-06-10T11:30:00"
      }
    ],
    "staff": [
      { "id_staff": 1, "firstname": "María", "lastname": "González", "role": "housekeeper" }
    ]
  }
}
```

#### `GET /housekeeping/tasks`
Lista tareas del día con filtros de estado y piso.

**Parámetros:** `date`, `status`, `id_staff`, `floor`

#### `POST /housekeeping/tasks/{id}/start`
Inicia una tarea de limpieza.

```json
{ "id_staff": 3 }
```

#### `POST /housekeeping/tasks/{id}/complete`
Completa una tarea con checklist y objetos encontrados.

```json
{
  "id_staff": 3,
  "notes":    "Pequeño daño en persiana reportado",
  "checklist": {
    "beds_made":          true,
    "bathroom_clean":     true,
    "floor_vacuumed":     true,
    "amenities_replaced": true,
    "minibar_checked":    true,
    "windows_cleaned":    false
  },
  "lost_items": [
    {
      "description": "Cargador iPhone blanco",
      "location":    "Cajón velador",
      "category":    "ELECTRONICS"
    }
  ]
}
```

---

### 📊 Dashboard

#### `GET /dashboard/kpis`
KPIs del día en tiempo real.

**Parámetros:** `date` (default: hoy), `id_property`

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "date": "2026-06-10",
    "occupancy": {
      "total_rooms":      80,
      "occupied":         62,
      "occupancy_pct":    77.5,
      "arrivals_total":   14,
      "arrivals_checked": 10,
      "arrivals_pending": 4,
      "departures":       11,
      "status":           "good"
    },
    "revenue": {
      "total":    5890000,
      "rooms":    5890000,
      "fnb":      0,
      "adr":      95000,
      "revpar":   73625,
      "currency": "CLP"
    },
    "operations": {
      "housekeeping": {
        "pending":     3,
        "in_progress": 2,
        "completed":   55,
        "dirty_rooms": 5
      }
    },
    "alerts": []
  }
}
```

#### `GET /dashboard/kpis/period`
KPIs para un período específico con datos para gráficas.

**Parámetros:** `from` (date), `to` (date), `id_property`

#### `GET /dashboard/alerts`
Alertas operacionales activas sin resolver.

#### `POST /dashboard/alerts/{id}/resolve`
Marca una alerta como resuelta.

---

### 💰 Tarifas

#### `GET /rates/availability`
Tarifas disponibles para fechas y tipo de habitación.

**Parámetros:** `id_room_type`, `check_in`, `check_out`, `channel`, `adults`

#### `GET /rates/yield/suggestions`
Sugerencias del Yield Manager para una fecha objetivo.

**Parámetros:** `date`, `id_property`

---

## Códigos de error

| Código | Significado                              |
|--------|------------------------------------------|
| 400    | Parámetros inválidos o faltantes         |
| 401    | API key inválida o ausente               |
| 403    | Sin permisos para este endpoint          |
| 404    | Recurso no encontrado                    |
| 429    | Rate limit excedido (100 req/min)        |
| 503    | Módulo no disponible                     |
| 500    | Error interno del servidor               |

**Formato de error:**
```json
{
  "success": false,
  "error": {
    "code":    404,
    "message": "Reserva no encontrada"
  },
  "meta": { "version": "v1" }
}
```

---

## Webhooks

### WhatsApp Business API
**Endpoint:** `POST /webhook/whatsapp`  
**Verificación:** `GET /webhook/whatsapp?hub.verify_token=<SMART_CC_WEBHOOK_VERIFY_TOKEN>`

---

## Rate Limits

- **100 requests/minuto** por API Key
- **Rate limit header:** no se incluye por defecto
- En caso de exceder el límite: HTTP 429

---

## Versionado

La API sigue [Semantic Versioning](https://semver.org/).  
La versión actual `v1` es estable.  
Breaking changes se anunciarán con 90 días de anticipación.

---

*SmartHotelOS API — Grupo Smart de Administración*
