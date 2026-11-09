# Demo comercial — Hotel Smart Santiago

Demo autocontenida (un solo HTML, sin backend) para mostrar a clientes hoteleros:

| Vista | URL | Qué muestra |
|---|---|---|
| Vitrina | `index.html#hotel` | Landing del hotel con ofertas y cupos en tiempo real |
| Mi pase | `index.html#pase` | Pase QR firmado (HMAC-SHA256) para entrada y salida |
| Recepción | `index.html#recepcion` | Panel con PIN demo `2468`: validar pase, registrar entrada/salida, anular, gestionar cupos y bitácora encadenada (SHA-256) |

Abrir: `open demo/index.html` o servir con `python3 -m http.server` desde esta carpeta.

Los datos viven en `localStorage` del navegador (botón **Reiniciar demo** en Recepción).
Para producción, la firma y la bitácora deben moverse al backend (`smart-api`) — la clave HMAC nunca debe estar en el cliente.
