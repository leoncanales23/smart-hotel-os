/**
 * SmartHotelOS — Dashboard Ejecutivo Vue.js 3
 * Grupo Smart de Administración
 *
 * Dashboard en tiempo real con KPIs hoteleros:
 * ocupación, RevPAR, ADR, ingresos por departamento,
 * alertas operacionales y gráficas comparativas.
 *
 * @file    smart-modules/smart_dashboard/views/js/ExecutiveDashboard.js
 * @version 1.0.0
 */

import {
  createApp, ref, computed, onMounted, onUnmounted, watch
} from 'vue';

// ── Chart.js dinámico ──────────────────────────────────────────
let Chart = null;
async function loadChart() {
  if (!Chart) {
    const mod = await import('https://cdn.jsdelivr.net/npm/chart.js@4/auto/auto.min.js');
    Chart = mod.default;
  }
  return Chart;
}

// ── Formatters ─────────────────────────────────────────────────
const fmt = {
  currency: (v, currency = 'CLP') =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency, maximumFractionDigits: 0 }).format(v || 0),
  percent:  (v) => `${(+v || 0).toFixed(1)}%`,
  number:   (v) => new Intl.NumberFormat('es-CL').format(+v || 0),
  change:   (v) => `${v >= 0 ? '+' : ''}${(+v || 0).toFixed(1)}%`,
};

// ── Composable: llamadas a API ─────────────────────────────────
function useDashboardApi(apiKey, idProperty) {
  const BASE = '/api/smart/v1';
  const H = { 'X-API-Key': apiKey };

  async function get(path, params = {}) {
    const qs  = new URLSearchParams({ id_property: idProperty, ...params }).toString();
    const res = await fetch(`${BASE}${path}?${qs}`, { headers: H });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const j = await res.json();
    return j.data;
  }

  return { get };
}

// ── Composable: WebSocket RT ───────────────────────────────────
function useRealtimeUpdates(onUpdate) {
  const live = ref(false);
  let ws, ping;

  function connect() {
    try {
      const proto = location.protocol === 'https:' ? 'wss' : 'ws';
      ws = new WebSocket(`${proto}://${location.host}/ws/smart-hotel`);
      ws.onopen  = () => {
        live.value = true;
        ws.send(JSON.stringify({ type: 'subscribe', channel: 'dashboard' }));
        ping = setInterval(() => ws.readyState === 1 && ws.send('{"type":"ping"}'), 30000);
      };
      ws.onmessage = ({ data }) => {
        try { const m = JSON.parse(data); if (m.type !== 'pong') onUpdate(m); } catch {}
      };
      ws.onclose = () => {
        live.value = false;
        clearInterval(ping);
        setTimeout(connect, 5000);
      };
    } catch { /* polling fallback */ }
  }

  const disconnect = () => { clearInterval(ping); ws?.close(); };
  return { live, connect, disconnect };
}

// ── Componente principal ───────────────────────────────────────
const ExecutiveDashboard = {
  name: 'ExecutiveDashboard',

  setup() {
    const apiKey     = ref(window.SMART_API_KEY     || '');
    const idProperty = ref(window.SMART_PROPERTY_ID || 1);
    const loading    = ref(true);
    const kpis       = ref(null);
    const period     = ref('today');   // today | week | month | ytd
    const periodData = ref(null);
    const alerts     = ref([]);
    const now        = ref(new Date());
    const chartRef   = ref(null);
    let chartInstance = null;
    let pollTimer;

    const api = useDashboardApi(apiKey.value, idProperty.value);

    // ── Computed ──────────────────────────────────────────
    const occ = computed(() => kpis.value?.occupancy ?? {});
    const rev = computed(() => kpis.value?.revenue   ?? {});
    const ops = computed(() => kpis.value?.operations ?? {});
    const tgt = computed(() => kpis.value?.targets    ?? {});
    const yoy = computed(() => kpis.value?.yoy        ?? {});

    const occColor = computed(() => {
      const pct = occ.value.occupancy_pct ?? 0;
      if (pct >= 90) return '#ef4444';
      if (pct >= 70) return '#22c55e';
      if (pct >= 50) return '#f59e0b';
      return '#ef4444';
    });

    const criticalAlerts = computed(() => alerts.value.filter(a => a.severity === 'critical'));
    const warningAlerts  = computed(() => alerts.value.filter(a => a.severity === 'warning'));

    // ── Métodos ───────────────────────────────────────────
    async function loadKpis() {
      try {
        const data = await api.get('/dashboard/kpis');
        kpis.value   = data;
        alerts.value = data.alerts ?? [];
      } catch (e) {
        console.error('Error cargando KPIs:', e);
      } finally {
        loading.value = false;
      }
    }

    async function loadPeriodData() {
      const today = new Date();
      let from, to = today.toISOString().slice(0, 10);
      switch (period.value) {
        case 'week':
          from = new Date(today - 6 * 86400000).toISOString().slice(0, 10); break;
        case 'month':
          from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10); break;
        case 'ytd':
          from = `${today.getFullYear()}-01-01`; break;
        default:
          from = to;
      }
      try {
        periodData.value = await api.get('/dashboard/kpis/period', { from, to });
        await renderChart(periodData.value?.chart_data);
      } catch (e) { console.error('Error período:', e); }
    }

    async function renderChart(data) {
      if (!data || !chartRef.value) return;
      await loadChart();
      if (chartInstance) { chartInstance.destroy(); }

      chartInstance = new Chart(chartRef.value, {
        type: 'bar',
        data: {
          labels: data.labels ?? [],
          datasets: [
            {
              type: 'bar',
              label: 'RevPAR',
              data: data.revpar ?? [],
              backgroundColor: 'rgba(201,168,76,0.3)',
              borderColor: 'rgba(201,168,76,0.8)',
              borderWidth: 1,
              yAxisID: 'y',
            },
            {
              type: 'line',
              label: 'Ocupación %',
              data: data.occ ?? [],
              borderColor: '#22c55e',
              backgroundColor: 'transparent',
              pointBackgroundColor: '#22c55e',
              borderWidth: 2,
              tension: 0.4,
              yAxisID: 'y1',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: '#C8C2B0', font: { family: 'DM Sans' } } },
            tooltip: {
              callbacks: {
                label: (ctx) => ctx.datasetIndex === 0
                  ? ` RevPAR: ${fmt.currency(ctx.raw, 'CLP')}`
                  : ` Ocupación: ${ctx.raw}%`,
              },
            },
          },
          scales: {
            x:  { ticks: { color: '#888', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y:  { position: 'left',  ticks: { color: '#C9A84C', callback: v => fmt.currency(v) }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y1: { position: 'right', ticks: { color: '#22c55e', callback: v => v + '%' }, grid: { display: false }, min: 0, max: 100 },
          },
        },
      });
    }

    async function resolveAlert(id) {
      try {
        await api.get(`/dashboard/alerts/${id}/resolve`);
        alerts.value = alerts.value.filter(a => a.id != id);
      } catch (e) { console.error(e); }
    }

    function handleRealtimeUpdate(msg) {
      if (msg.type === 'kpi_update') {
        if (msg.occupancy) kpis.value.occupancy = { ...kpis.value.occupancy, ...msg.occupancy };
        if (msg.revenue)   kpis.value.revenue   = { ...kpis.value.revenue,   ...msg.revenue };
      }
      if (msg.type === 'new_alert') {
        alerts.value.unshift(msg.alert);
      }
      if (msg.type === 'alert_resolved') {
        alerts.value = alerts.value.filter(a => a.id != msg.id);
      }
    }

    const ws = useRealtimeUpdates(handleRealtimeUpdate);

    // Recargar al cambiar período
    watch(period, loadPeriodData);

    onMounted(async () => {
      await loadKpis();
      await loadPeriodData();
      ws.connect();

      // Polling cada 60s
      pollTimer = setInterval(loadKpis, 60000);

      // Reloj
      setInterval(() => { now.value = new Date(); }, 1000);
    });

    onUnmounted(() => {
      ws.disconnect();
      clearInterval(pollTimer);
      chartInstance?.destroy();
    });

    return {
      loading, kpis, period, periodData, alerts, now, chartRef,
      occ, rev, ops, tgt, yoy,
      occColor, criticalAlerts, warningAlerts,
      ws, fmt,
      loadKpis, loadPeriodData, resolveAlert,
    };
  },

  template: `
    <div class="smart-dash" :class="{ 'smart-dash--loading': loading }">

      <!-- Barra de estado superior -->
      <div class="smart-dash__topbar">
        <div class="smart-dash__brand">
          <span class="smart-dash__brand-name">SmartHotelOS</span>
          <span class="smart-dash__brand-sep">·</span>
          <span class="smart-dash__brand-date">
            {{ now.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
          </span>
        </div>
        <div class="smart-dash__topbar-right">
          <span class="smart-dash__live-indicator" :class="{ active: ws.live.value }">
            <span class="smart-dash__live-dot"></span>
            {{ ws.live.value ? 'En vivo' : 'Actualización manual' }}
          </span>
          <span class="smart-dash__clock">{{ now.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) }}</span>
          <button @click="loadKpis" class="smart-dash__refresh-btn" title="Actualizar KPIs">↻</button>
        </div>
      </div>

      <!-- Alertas críticas (banner) -->
      <div v-if="criticalAlerts.length" class="smart-dash__alert-banner">
        <span class="smart-dash__alert-icon">⚠</span>
        <div class="smart-dash__alert-list">
          <div v-for="alert in criticalAlerts" :key="alert.id" class="smart-dash__alert-item">
            <strong>{{ alert.title }}</strong>
            <button @click="resolveAlert(alert.id)" class="smart-dash__alert-dismiss">✕</button>
          </div>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="smart-dash__skeleton">
        <div v-for="i in 6" :key="i" class="smart-dash__skeleton-card"></div>
      </div>

      <template v-else>

        <!-- ── KPI CARDS PRINCIPALES ── -->
        <div class="smart-dash__kpi-grid">

          <!-- Ocupación -->
          <div class="smart-kpi-card smart-kpi-card--primary">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">Ocupación</span>
              <span class="smart-kpi-card__badge" :style="{ background: occColor + '22', color: occColor }">
                {{ occ.status?.replace('_', ' ') }}
              </span>
            </div>
            <div class="smart-kpi-card__value" :style="{ color: occColor }">
              {{ fmt.percent(occ.occupancy_pct) }}
            </div>
            <div class="smart-kpi-card__sub">
              {{ occ.occupied }} / {{ occ.total_rooms }} habitaciones ocupadas
            </div>
            <div class="smart-kpi-card__progress">
              <div class="smart-kpi-card__progress-bar"
                   :style="{ width: occ.occupancy_pct + '%', background: occColor }"></div>
            </div>
            <div class="smart-kpi-card__footer">
              <span>Objetivo: {{ fmt.percent(tgt.target_occupancy) }}</span>
              <span class="smart-kpi-change" :class="{ positive: yoy.occupancy_change_pts >= 0 }">
                {{ yoy.occupancy_change_pts >= 0 ? '+' : '' }}{{ yoy.occupancy_change_pts }}pts vs año ant.
              </span>
            </div>
          </div>

          <!-- RevPAR -->
          <div class="smart-kpi-card">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">RevPAR</span>
              <span class="smart-kpi-card__icon">💰</span>
            </div>
            <div class="smart-kpi-card__value">
              {{ fmt.currency(rev.revpar, rev.currency) }}
            </div>
            <div class="smart-kpi-card__sub">Revenue por hab. disponible</div>
            <div class="smart-kpi-card__footer">
              <span>Obj: {{ fmt.currency(tgt.target_revpar, rev.currency) }}</span>
              <span class="smart-kpi-change" :class="{ positive: yoy.revenue_change_pct >= 0 }">
                {{ fmt.change(yoy.revenue_change_pct) }} YoY
              </span>
            </div>
          </div>

          <!-- ADR -->
          <div class="smart-kpi-card">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">ADR</span>
              <span class="smart-kpi-card__icon">🏷</span>
            </div>
            <div class="smart-kpi-card__value">
              {{ fmt.currency(rev.adr, rev.currency) }}
            </div>
            <div class="smart-kpi-card__sub">Tarifa diaria promedio</div>
            <div class="smart-kpi-card__footer">
              <span>Objetivo: {{ fmt.currency(tgt.target_adr, rev.currency) }}</span>
            </div>
          </div>

          <!-- Ingresos totales -->
          <div class="smart-kpi-card">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">Ingresos del día</span>
              <span class="smart-kpi-card__icon">📊</span>
            </div>
            <div class="smart-kpi-card__value">
              {{ fmt.currency(rev.total, rev.currency) }}
            </div>
            <div class="smart-kpi-card__sub">
              <span>Hab: {{ fmt.currency(rev.rooms) }}</span>
              <span v-if="rev.fnb > 0"> · F&B: {{ fmt.currency(rev.fnb) }}</span>
              <span v-if="rev.spa > 0"> · Spa: {{ fmt.currency(rev.spa) }}</span>
            </div>
            <div class="smart-kpi-card__footer">
              <span>TRevPAR: {{ fmt.currency(rev.trevpar) }}</span>
            </div>
          </div>

          <!-- Llegadas del día -->
          <div class="smart-kpi-card">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">Llegadas hoy</span>
              <span class="smart-kpi-card__icon">➜</span>
            </div>
            <div class="smart-kpi-card__value">{{ occ.arrivals_total }}</div>
            <div class="smart-kpi-card__sub">
              <span class="text-green">{{ occ.arrivals_checked }} check-in</span>
              · <span class="text-amber">{{ occ.arrivals_pending }} pendientes</span>
            </div>
            <div class="smart-kpi-card__footer">
              <span>Salidas: {{ occ.departures }}</span>
              <span v-if="occ.no_shows > 0" class="text-red">No shows: {{ occ.no_shows }}</span>
            </div>
          </div>

          <!-- Housekeeping -->
          <div class="smart-kpi-card">
            <div class="smart-kpi-card__header">
              <span class="smart-kpi-card__label">Housekeeping</span>
              <span class="smart-kpi-card__icon">🛏</span>
            </div>
            <div class="smart-kpi-card__value">
              {{ ops.housekeeping?.clean_rooms ?? 0 }}
              <span style="font-size:1rem; color:#6b7280"> limpias</span>
            </div>
            <div class="smart-kpi-card__sub">
              <span class="text-amber">{{ ops.housekeeping?.dirty_rooms ?? 0 }} sucias</span>
              · <span class="text-purple">{{ ops.housekeeping?.in_progress ?? 0 }} en limpieza</span>
            </div>
            <div class="smart-kpi-card__footer">
              <span>Completadas: {{ ops.housekeeping?.completed ?? 0 }}</span>
              <span v-if="ops.housekeeping?.pending > 0" class="text-amber">
                {{ ops.housekeeping.pending }} pendientes
              </span>
            </div>
          </div>

        </div>

        <!-- ── GRÁFICA + ALERTAS ── -->
        <div class="smart-dash__bottom-grid">

          <!-- Gráfica de tendencia -->
          <div class="smart-dash__chart-card">
            <div class="smart-dash__chart-header">
              <h3>Tendencia — RevPAR y Ocupación</h3>
              <div class="smart-dash__period-selector">
                <button v-for="p in [{v:'today',l:'Hoy'},{v:'week',l:'7 días'},{v:'month',l:'Mes'},{v:'ytd',l:'YTD'}]"
                        :key="p.v"
                        @click="period = p.v"
                        :class="{ active: period === p.v }">
                  {{ p.l }}
                </button>
              </div>
            </div>
            <div class="smart-dash__chart-wrap">
              <canvas ref="chartRef" height="220"></canvas>
            </div>
            <div v-if="periodData" class="smart-dash__chart-summary">
              <span>Total período: <strong>{{ fmt.currency(periodData.total_revenue) }}</strong></span>
              <span>Reservas: <strong>{{ periodData.total_bookings }}</strong></span>
              <span>Estancia media: <strong>{{ periodData.avg_length_stay }} noches</strong></span>
            </div>
          </div>

          <!-- Panel de alertas -->
          <div class="smart-dash__alerts-card">
            <div class="smart-dash__alerts-header">
              <h3>Alertas Operacionales</h3>
              <span class="smart-dash__alerts-count"
                    :class="{ urgent: criticalAlerts.length > 0 }">
                {{ alerts.length }}
              </span>
            </div>
            <div v-if="alerts.length === 0" class="smart-dash__alerts-empty">
              <span>✓</span> Sin alertas activas
            </div>
            <div v-else class="smart-dash__alerts-list">
              <div v-for="alert in alerts" :key="alert.id"
                   class="smart-dash__alert"
                   :class="'smart-dash__alert--' + alert.severity">
                <div class="smart-dash__alert-body">
                  <div class="smart-dash__alert-sev">{{ alert.severity.toUpperCase() }}</div>
                  <div class="smart-dash__alert-title">{{ alert.title }}</div>
                  <div v-if="alert.message" class="smart-dash__alert-msg">{{ alert.message }}</div>
                </div>
                <button @click="resolveAlert(alert.id)" class="smart-dash__alert-resolve">
                  ✓ Resolver
                </button>
              </div>
            </div>
          </div>

        </div>

      </template>
    </div>
  `,
};

// ── Inicialización ─────────────────────────────────────────────
export function initExecutiveDashboard(selector = '#smart-executive-dashboard') {
  const app = createApp(ExecutiveDashboard);
  app.mount(selector);
  return app;
}

if (document.getElementById('smart-executive-dashboard')) {
  initExecutiveDashboard();
}
