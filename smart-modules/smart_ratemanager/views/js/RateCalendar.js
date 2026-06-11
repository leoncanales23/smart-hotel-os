/**
 * SmartHotelOS — Calendario de Tarifas
 * Grupo Smart de Administración
 *
 * Visualización y edición de tarifas en calendario mensual.
 * Permite ver, comparar y actualizar precios por tipo de habitación,
 * canal y plan tarifario con soporte de yield suggestions.
 *
 * @file    smart-modules/smart_ratemanager/views/js/RateCalendar.js
 * @version 1.0.0
 */

import { createApp, ref, computed, onMounted, watch } from 'vue';

const MEAL_LABELS = { RO: 'Solo alojamiento', BB: 'B&B', HB: 'Media pensión', FB: 'Pensión completa', AI: 'Todo incluido' };
const CHANNELS    = ['DIRECT_WEB', 'BOOKING_COM', 'EXPEDIA', 'GDS', 'CORPORATE'];

const RateCalendar = {
  name: 'RateCalendar',

  setup() {
    const apiKey     = ref(window.SMART_API_KEY     || '');
    const idProperty = ref(window.SMART_PROPERTY_ID || 1);

    // Estado del calendario
    const viewYear    = ref(new Date().getFullYear());
    const viewMonth   = ref(new Date().getMonth()); // 0-indexed
    const roomTypes   = ref([]);
    const selectedRT  = ref(null);
    const selectedCh  = ref('DIRECT_WEB');
    const rates       = ref({});        // { 'YYYY-MM-DD': { price, plan, ...} }
    const yield_hints = ref({});        // { 'YYYY-MM-DD': { suggestion, multiplier } }
    const loading     = ref(false);
    const editMode    = ref(false);
    const selectedCell= ref(null);
    const bulkEdit    = ref({ open: false, from: '', to: '', price: '', plan_id: '' });
    const ratePlans   = ref([]);
    const notifications = ref([]);

    // ── Computed ──────────────────────────────────────────
    const monthName = computed(() => {
      return new Date(viewYear.value, viewMonth.value, 1)
        .toLocaleDateString('es-CL', { month: 'long', year: 'numeric' });
    });

    const calendarDays = computed(() => {
      const year  = viewYear.value;
      const month = viewMonth.value;
      const first = new Date(year, month, 1).getDay(); // 0=Dom
      const days  = new Date(year, month + 1, 0).getDate();
      const cells = [];

      // Días del mes anterior (relleno)
      const prevDays = new Date(year, month, 0).getDate();
      for (let i = first - 1; i >= 0; i--) {
        cells.push({ day: prevDays - i, current: false, date: null });
      }
      // Días del mes actual
      for (let d = 1; d <= days; d++) {
        const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({ day: d, current: true, date: dateStr });
      }
      // Días del mes siguiente (relleno hasta completar 6 filas)
      let next = 1;
      while (cells.length % 7 !== 0) {
        cells.push({ day: next++, current: false, date: null });
      }
      return cells;
    });

    const weeks = computed(() => {
      const c = calendarDays.value;
      const w = [];
      for (let i = 0; i < c.length; i += 7) { w.push(c.slice(i, i+7)); }
      return w;
    });

    const totalMonthRevenue = computed(() => {
      return Object.values(rates.value)
        .filter(r => r?.price > 0)
        .reduce((sum, r) => sum + r.price, 0);
    });

    // ── Métodos ───────────────────────────────────────────
    async function fetchRates() {
      if (!selectedRT.value) return;
      loading.value = true;
      try {
        const year  = viewYear.value;
        const month = viewMonth.value + 1;
        const from  = `${year}-${String(month).padStart(2,'0')}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        const to    = `${year}-${String(month).padStart(2,'0')}-${lastDay}`;

        const res = await fetch(
          `/api/smart/v1/rates/availability?id_room_type=${selectedRT.value}&check_in=${from}&check_out=${to}&channel=${selectedCh.value}&id_property=${idProperty.value}`,
          { headers: { 'X-API-Key': apiKey.value } }
        );
        const data = await res.json();

        // Construir mapa de fechas
        const newRates = {};
        if (data.success && data.data?.daily_rates) {
          for (const dr of data.data.daily_rates) {
            newRates[dr.date] = dr;
          }
        }
        rates.value = newRates;

        // Obtener sugerencias de yield para el mes
        await fetchYieldHints(from, to);
      } catch (e) {
        showNotif('Error cargando tarifas: ' + e.message, 'error');
      } finally {
        loading.value = false;
      }
    }

    async function fetchYieldHints(from, to) {
      try {
        const res = await fetch(
          `/api/smart/v1/rates/yield/suggestions?from=${from}&to=${to}&id_property=${idProperty.value}`,
          { headers: { 'X-API-Key': apiKey.value } }
        );
        const data = await res.json();
        if (data.success) {
          const hints = {};
          for (const s of data.data?.suggestions || []) {
            hints[s.date] = s;
          }
          yield_hints.value = hints;
        }
      } catch {}
    }

    async function fetchRatePlans() {
      try {
        const res = await fetch(
          `/api/smart/v1/rates/plans?id_property=${idProperty.value}`,
          { headers: { 'X-API-Key': apiKey.value } }
        );
        const data = await res.json();
        if (data.success) ratePlans.value = data.data?.plans || [];
      } catch {}
    }

    async function updateRate(dateStr, newPrice) {
      if (!editMode.value || !dateStr) return;
      const rateEntry = rates.value[dateStr];
      if (!rateEntry?.id_rate) {
        showNotif('No hay tarifa base para esta fecha', 'error');
        return;
      }
      try {
        const res = await fetch(`/api/smart/v1/rates/${rateEntry.id_rate}`, {
          method: 'PUT',
          headers: { 'X-API-Key': apiKey.value, 'Content-Type': 'application/json' },
          body: JSON.stringify({ price: newPrice, reason: 'Manual — Rate Calendar' }),
        });
        const data = await res.json();
        if (data.success) {
          rates.value[dateStr] = { ...rateEntry, price: newPrice };
          showNotif(`Tarifa actualizada: ${formatCLP(newPrice)}`, 'success');
        }
      } catch (e) {
        showNotif('Error actualizando tarifa', 'error');
      }
    }

    async function applyBulkUpdate() {
      const { from, to, price, plan_id } = bulkEdit.value;
      if (!from || !to || !price) { showNotif('Completa todos los campos', 'error'); return; }

      const daysInRange = getDatesInRange(from, to);
      let updated = 0;

      for (const dateStr of daysInRange) {
        const rateEntry = rates.value[dateStr];
        if (rateEntry?.id_rate) {
          await updateRate(dateStr, parseFloat(price));
          updated++;
        }
      }

      showNotif(`${updated} tarifas actualizadas en el período`, 'success');
      bulkEdit.value = { open: false, from: '', to: '', price: '', plan_id: '' };
    }

    function prevMonth() {
      if (viewMonth.value === 0) { viewYear.value--; viewMonth.value = 11; }
      else { viewMonth.value--; }
    }

    function nextMonth() {
      if (viewMonth.value === 11) { viewYear.value++; viewMonth.value = 0; }
      else { viewMonth.value++; }
    }

    function getCellColor(dateStr) {
      const r = rates.value[dateStr];
      if (!r) return 'transparent';

      const hint = yield_hints.value[dateStr];
      if (hint?.multiplier > 1.1) return 'rgba(239,68,68,0.15)';   // rojo = subir precio
      if (hint?.multiplier < 0.95) return 'rgba(59,130,246,0.15)'; // azul = bajar precio

      if (r.is_closed || r.is_stop_sell) return 'rgba(107,101,96,0.2)';
      if (r.price >= 100000) return 'rgba(201,168,76,0.15)';
      if (r.price >= 70000)  return 'rgba(34,197,94,0.1)';
      return 'rgba(255,255,255,0.03)';
    }

    function formatCLP(v) {
      return '$' + new Intl.NumberFormat('es-CL').format(Math.round(v || 0));
    }

    function getDatesInRange(from, to) {
      const dates = [];
      let cur = new Date(from);
      const end = new Date(to);
      while (cur <= end) {
        dates.push(cur.toISOString().slice(0, 10));
        cur.setDate(cur.getDate() + 1);
      }
      return dates;
    }

    function showNotif(msg, type = 'info') {
      const id = Date.now();
      notifications.value.push({ id, msg, type });
      setTimeout(() => { notifications.value = notifications.value.filter(n => n.id !== id); }, 3500);
    }

    function isPast(dateStr) { return dateStr && dateStr < new Date().toISOString().slice(0, 10); }
    function isToday(dateStr) { return dateStr === new Date().toISOString().slice(0, 10); }
    function isWeekend(dateStr) {
      if (!dateStr) return false;
      const d = new Date(dateStr).getDay();
      return d === 0 || d === 6;
    }

    watch([selectedRT, selectedCh, viewYear, viewMonth], fetchRates);

    onMounted(async () => {
      await fetchRatePlans();
      // Cargar tipos de habitación desde la API
      try {
        const res = await fetch(
          `/api/smart/v1/rooms/types?id_property=${idProperty.value}`,
          { headers: { 'X-API-Key': apiKey.value } }
        );
        const data = await res.json();
        if (data.success) {
          roomTypes.value = data.data?.room_types || [];
          if (roomTypes.value.length) {
            selectedRT.value = roomTypes.value[0].id_room_type;
          }
        }
      } catch {}
    });

    return {
      viewYear, viewMonth, monthName, weeks, roomTypes, selectedRT, selectedCh,
      ratePlans, rates, yield_hints, loading, editMode, selectedCell, bulkEdit, notifications,
      totalMonthRevenue, CHANNELS, MEAL_LABELS,
      fetchRates, updateRate, applyBulkUpdate,
      prevMonth, nextMonth, getCellColor, formatCLP, isPast, isToday, isWeekend, showNotif,
    };
  },

  template: `
    <div class="smart-rate-calendar">

      <!-- Notificaciones -->
      <div class="smart-notifications">
        <div v-for="n in notifications" :key="n.id"
             class="smart-notification" :class="'smart-notification--' + n.type">
          {{ n.msg }}
        </div>
      </div>

      <!-- Toolbar del calendario -->
      <div class="smart-rc-toolbar">
        <div class="smart-rc-toolbar__left">
          <!-- Selector de tipo de habitación -->
          <select v-model="selectedRT" class="smart-select" style="min-width:160px">
            <option v-for="rt in roomTypes" :key="rt.id_room_type" :value="rt.id_room_type">
              {{ rt.name }}
            </option>
          </select>
          <!-- Selector de canal -->
          <select v-model="selectedCh" class="smart-select">
            <option v-for="ch in CHANNELS" :key="ch" :value="ch">{{ ch.replace('_',' ') }}</option>
          </select>
        </div>

        <div class="smart-rc-toolbar__center">
          <button @click="prevMonth" class="smart-btn smart-btn--ghost smart-btn--sm">←</button>
          <span class="smart-rc-month-label">{{ monthName }}</span>
          <button @click="nextMonth" class="smart-btn smart-btn--ghost smart-btn--sm">→</button>
        </div>

        <div class="smart-rc-toolbar__right">
          <button @click="editMode = !editMode"
                  :class="['smart-btn smart-btn--sm', editMode ? 'smart-btn--primary' : 'smart-btn--ghost']">
            {{ editMode ? '✓ Modo edición ON' : '✎ Editar tarifas' }}
          </button>
          <button @click="bulkEdit.open = true" class="smart-btn smart-btn--ghost smart-btn--sm">
            ⊞ Actualización masiva
          </button>
        </div>
      </div>

      <!-- Leyenda -->
      <div class="smart-rc-legend">
        <span><span class="smart-rc-legend-dot" style="background:rgba(201,168,76,0.4)"></span> Alta demanda</span>
        <span><span class="smart-rc-legend-dot" style="background:rgba(34,197,94,0.3)"></span> Normal</span>
        <span><span class="smart-rc-legend-dot" style="background:rgba(239,68,68,0.3)"></span> Yield: subir</span>
        <span><span class="smart-rc-legend-dot" style="background:rgba(59,130,246,0.3)"></span> Yield: bajar</span>
        <span><span class="smart-rc-legend-dot" style="background:rgba(107,101,96,0.3)"></span> Cerrada</span>
        <span style="margin-left:auto; font-family:monospace; font-size:0.7rem; color:#C9A84C">
          Mes total: {{ formatCLP(totalMonthRevenue) }}
        </span>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="smart-loading">
        <div class="smart-loading-spinner"></div>
        <span>Cargando tarifas...</span>
      </div>

      <!-- Calendario -->
      <div v-else class="smart-rc-grid">
        <!-- Cabecera días -->
        <div class="smart-rc-header-row">
          <div v-for="d in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="d"
               class="smart-rc-header-cell">{{ d }}</div>
        </div>

        <!-- Semanas -->
        <div v-for="(week, wi) in weeks" :key="wi" class="smart-rc-week">
          <div v-for="cell in week" :key="cell.date || cell.day + '-' + wi"
               class="smart-rc-cell"
               :class="{
                 'smart-rc-cell--other':   !cell.current,
                 'smart-rc-cell--today':   isToday(cell.date),
                 'smart-rc-cell--past':    isPast(cell.date),
                 'smart-rc-cell--weekend': isWeekend(cell.date),
                 'smart-rc-cell--closed':  cell.date && rates[cell.date]?.is_stop_sell,
                 'smart-rc-cell--selected':selectedCell === cell.date,
               }"
               :style="cell.date ? { background: getCellColor(cell.date) } : {}"
               @click="cell.current && cell.date && (selectedCell = selectedCell === cell.date ? null : cell.date)">

            <span class="smart-rc-cell__day">{{ cell.day }}</span>

            <template v-if="cell.date && rates[cell.date]">
              <span class="smart-rc-cell__price" :class="{ 'smart-rc-cell__price--high': rates[cell.date].price >= 100000 }">
                {{ formatCLP(rates[cell.date].price) }}
              </span>
              <span v-if="rates[cell.date].meal_plan" class="smart-rc-cell__meal">
                {{ rates[cell.date].meal_plan }}
              </span>
              <span v-if="yield_hints[cell.date]" class="smart-rc-cell__yield-hint"
                    :class="yield_hints[cell.date].multiplier > 1 ? 'up' : 'down'">
                {{ yield_hints[cell.date].multiplier > 1 ? '↑' : '↓' }}
              </span>
              <span v-if="rates[cell.date].is_closed || rates[cell.date].is_stop_sell"
                    class="smart-rc-cell__closed">STOP</span>
            </template>
            <span v-else-if="cell.current && cell.date" class="smart-rc-cell__no-rate">—</span>
          </div>
        </div>
      </div>

      <!-- Panel de edición de celda seleccionada -->
      <transition name="fade">
        <div v-if="selectedCell && rates[selectedCell] && editMode" class="smart-rc-edit-panel">
          <h4>Editar tarifa — {{ selectedCell }}</h4>
          <div class="smart-form-row">
            <div class="smart-form-group">
              <label>Precio (CLP)</label>
              <input type="number" :value="rates[selectedCell].price" step="1000"
                     class="smart-input" @change="updateRate(selectedCell, +$event.target.value)">
            </div>
            <div class="smart-form-group">
              <label>Estado</label>
              <select class="smart-select">
                <option value="0">Abierta</option>
                <option value="1">Stop sell</option>
              </select>
            </div>
          </div>
          <div v-if="yield_hints[selectedCell]" class="smart-rc-yield-card">
            <span class="smart-rc-yield-card__label">Sugerencia Yield</span>
            <span class="smart-rc-yield-card__mult"
                  :class="yield_hints[selectedCell].multiplier > 1 ? 'text-red' : 'text-blue'">
              ×{{ yield_hints[selectedCell].multiplier }}
            </span>
            <span class="smart-rc-yield-card__suggested">
              → {{ formatCLP(rates[selectedCell].price * yield_hints[selectedCell].multiplier) }}
            </span>
            <button @click="updateRate(selectedCell, rates[selectedCell].price * yield_hints[selectedCell].multiplier)"
                    class="smart-btn smart-btn--sm smart-btn--primary">
              Aplicar
            </button>
          </div>
        </div>
      </transition>

      <!-- Modal: actualización masiva -->
      <transition name="fade">
        <div v-if="bulkEdit.open" class="smart-modal-overlay" @click.self="bulkEdit.open=false">
          <div class="smart-modal">
            <div class="smart-modal__header">
              <h3>Actualización masiva de tarifas</h3>
              <button @click="bulkEdit.open=false" class="smart-modal__close">✕</button>
            </div>
            <div class="smart-modal__body">
              <div class="smart-form-row">
                <div class="smart-form-group">
                  <label>Fecha desde</label>
                  <input type="date" v-model="bulkEdit.from" class="smart-input">
                </div>
                <div class="smart-form-group">
                  <label>Fecha hasta</label>
                  <input type="date" v-model="bulkEdit.to" class="smart-input">
                </div>
              </div>
              <div class="smart-form-group">
                <label>Precio por noche (CLP)</label>
                <input type="number" v-model="bulkEdit.price" step="1000"
                       placeholder="95000" class="smart-input">
              </div>
            </div>
            <div class="smart-modal__footer">
              <button @click="bulkEdit.open=false" class="smart-btn smart-btn--ghost">Cancelar</button>
              <button @click="applyBulkUpdate" class="smart-btn smart-btn--primary">
                Aplicar a período
              </button>
            </div>
          </div>
        </div>
      </transition>
    </div>
  `,
};

// ── Estilos del calendario ────────────────────────────────────
const style = document.createElement('style');
style.textContent = `
.smart-rate-calendar { font-family: var(--sg-font-sans, 'DM Sans', sans-serif); color: #F0EDE4; }
.smart-rc-toolbar {
  display:flex; justify-content:space-between; align-items:center; gap:1rem;
  padding:1rem 0 1.25rem; flex-wrap:wrap;
}
.smart-rc-toolbar__left, .smart-rc-toolbar__right { display:flex; gap:0.5rem; }
.smart-rc-toolbar__center { display:flex; align-items:center; gap:1rem; }
.smart-rc-month-label {
  font-family: 'Cormorant Garamond', serif; font-size:1.3rem; font-weight:300;
  color:#F0EDE4; min-width:200px; text-align:center; text-transform:capitalize;
}
.smart-rc-legend {
  display:flex; gap:1.25rem; flex-wrap:wrap; align-items:center;
  padding:0.5rem 0; margin-bottom:0.75rem; font-size:0.68rem; color:#6B6560;
}
.smart-rc-legend-dot {
  display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; vertical-align:middle;
}
.smart-rc-grid { border:1px solid rgba(255,255,255,0.07); border-radius:4px; overflow:hidden; }
.smart-rc-header-row { display:grid; grid-template-columns:repeat(7,1fr); background:rgba(201,168,76,0.06); }
.smart-rc-header-cell {
  padding:0.5rem; text-align:center; font-size:0.62rem; letter-spacing:0.15em;
  text-transform:uppercase; color:#C9A84C; border-bottom:1px solid rgba(255,255,255,0.05);
}
.smart-rc-week { display:grid; grid-template-columns:repeat(7,1fr); }
.smart-rc-cell {
  min-height:72px; padding:0.4rem 0.5rem; cursor:pointer; position:relative;
  border:1px solid rgba(255,255,255,0.04); transition:background 0.2s;
  display:flex; flex-direction:column; gap:2px;
}
.smart-rc-cell:hover:not(.smart-rc-cell--other):not(.smart-rc-cell--past) {
  background:rgba(201,168,76,0.12) !important; border-color:rgba(201,168,76,0.3);
}
.smart-rc-cell--other { opacity:0.25; cursor:default; }
.smart-rc-cell--past  { opacity:0.4; cursor:default; }
.smart-rc-cell--today { border-color:rgba(201,168,76,0.4) !important; }
.smart-rc-cell--today .smart-rc-cell__day { color:#C9A84C; font-weight:600; }
.smart-rc-cell--weekend { background:rgba(255,255,255,0.02) !important; }
.smart-rc-cell--selected { border-color:rgba(201,168,76,0.7) !important; }
.smart-rc-cell--closed { opacity:0.5; }
.smart-rc-cell__day   { font-size:0.7rem; color:#6B6560; }
.smart-rc-cell__price {
  font-family:monospace; font-size:0.78rem; color:#F0EDE4; font-weight:500; line-height:1;
}
.smart-rc-cell__price--high { color:#C9A84C; }
.smart-rc-cell__meal   { font-size:0.55rem; color:#8B6D2C; letter-spacing:0.1em; }
.smart-rc-cell__no-rate{ font-size:0.65rem; color:#3a3a3a; }
.smart-rc-cell__closed { font-size:0.55rem; color:#ef4444; font-weight:700; letter-spacing:0.1em; }
.smart-rc-cell__yield-hint {
  position:absolute; top:4px; right:4px; font-size:0.65rem; font-weight:700;
}
.smart-rc-cell__yield-hint.up   { color:#ef4444; }
.smart-rc-cell__yield-hint.down { color:#3b82f6; }
.smart-rc-edit-panel {
  margin-top:1rem; padding:1.25rem; background:rgba(22,22,26,0.9);
  border:1px solid rgba(201,168,76,0.2); border-radius:4px;
}
.smart-rc-edit-panel h4 {
  font-family:'Cormorant Garamond',serif; font-size:1rem; font-weight:300;
  color:#F0EDE4; margin-bottom:0.75rem;
}
.smart-rc-yield-card {
  display:flex; align-items:center; gap:1rem; padding:0.75rem;
  background:rgba(201,168,76,0.08); border:1px solid rgba(201,168,76,0.15);
  border-radius:4px; margin-top:0.75rem; flex-wrap:wrap;
}
.smart-rc-yield-card__label { font-size:0.65rem; letter-spacing:0.15em; text-transform:uppercase; color:#8B6D2C; }
.smart-rc-yield-card__mult  { font-family:monospace; font-size:1.1rem; color:#F0EDE4; }
.smart-rc-yield-card__suggested { font-family:monospace; font-size:0.85rem; color:#C9A84C; }
.text-red  { color:#ef4444; } .text-blue { color:#3b82f6; }
`;
document.head.appendChild(style);

// ── Init ──────────────────────────────────────────────────────
export function initRateCalendar(selector = '#smart-rate-calendar') {
  createApp(RateCalendar).mount(selector);
}

if (document.getElementById('smart-rate-calendar')) {
  initRateCalendar();
}
