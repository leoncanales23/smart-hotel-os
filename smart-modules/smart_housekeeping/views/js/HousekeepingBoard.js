/**
 * SmartHotelOS — Panel de Housekeeping en Tiempo Real
 * Grupo Smart de Administración
 *
 * App Vue.js 3 para supervisoras de pisos:
 * visualización del estado de habitaciones por piso,
 * asignación de tareas y actualización en tiempo real vía WebSocket.
 *
 * @file    smart-modules/smart_housekeeping/views/js/HousekeepingBoard.js
 * @version 1.0.0
 */

import { createApp, ref, computed, onMounted, onUnmounted } from 'vue';

// ── Constantes ─────────────────────────────────────────────────
const ROOM_STATUS_LABELS = {
  CL:  { label: 'Limpia',           color: '#22c55e', icon: '✓' },
  IN:  { label: 'Inspeccionada',    color: '#10b981', icon: '★' },
  VC:  { label: 'Vacante Limpia',   color: '#86efac', icon: '◎' },
  VD:  { label: 'Vacante Sucia',    color: '#f59e0b', icon: '○' },
  OD:  { label: 'Ocupada Sucia',    color: '#ef4444', icon: '●' },
  OC:  { label: 'Ocupada Limpia',   color: '#3b82f6', icon: '●' },
  IP:  { label: 'En Limpieza',      color: '#8b5cf6', icon: '⟳' },
  DND: { label: 'No Molestar',      color: '#6b7280', icon: '⊘' },
  OO:  { label: 'Fuera de Servicio',color: '#dc2626', icon: '✕' },
  OS:  { label: 'Fuera Temporal',   color: '#d97706', icon: '!' },
  PU:  { label: 'Retoque',          color: '#0ea5e9', icon: '↻' },
  DI:  { label: 'Sucia',            color: '#f97316', icon: '○' },
};

const TASK_STATUS_LABELS = {
  PENDING:     { label: 'Pendiente',    color: '#f59e0b' },
  ASSIGNED:    { label: 'Asignada',     color: '#3b82f6' },
  IN_PROGRESS: { label: 'En curso',     color: '#8b5cf6' },
  COMPLETED:   { label: 'Completada',   color: '#22c55e' },
  CANCELLED:   { label: 'Cancelada',    color: '#6b7280' },
};

// ── Composables ────────────────────────────────────────────────

/**
 * Manejo del WebSocket para actualizaciones en tiempo real.
 */
function useWebSocket(onMessage) {
  const connected  = ref(false);
  const reconnects = ref(0);
  let ws           = null;
  let pingInterval = null;

  function connect() {
    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    const url      = `${protocol}//${location.host}/ws/smart-hotel`;

    try {
      ws = new WebSocket(url);

      ws.onopen = () => {
        connected.value  = true;
        reconnects.value = 0;
        // Suscribirse al canal de housekeeping
        ws.send(JSON.stringify({ type: 'subscribe', channel: 'housekeeping' }));
        // Ping cada 30s para mantener viva la conexión
        pingInterval = setInterval(() => ws.readyState === WebSocket.OPEN && ws.send('{"type":"ping"}'), 30000);
      };

      ws.onmessage = ({ data }) => {
        try {
          const msg = JSON.parse(data);
          if (msg.type !== 'pong') onMessage(msg);
        } catch (e) { /* ignorar */ }
      };

      ws.onclose = () => {
        connected.value = false;
        clearInterval(pingInterval);
        // Reconectar con backoff exponencial
        const delay = Math.min(1000 * Math.pow(2, reconnects.value), 30000);
        reconnects.value++;
        setTimeout(connect, delay);
      };

      ws.onerror = () => ws.close();
    } catch (e) {
      // WebSocket no disponible, usar polling
      console.warn('WebSocket no disponible, usando polling cada 30s');
    }
  }

  function disconnect() {
    clearInterval(pingInterval);
    if (ws) { ws.close(); ws = null; }
  }

  return { connected, reconnects, connect, disconnect };
}

/**
 * Llamadas a la API REST de SmartHotelOS.
 */
function useSmartApi(apiKey) {
  const BASE = '/api/smart/v1';
  const headers = {
    'Content-Type': 'application/json',
    'X-API-Key': apiKey,
  };

  async function get(path, params = {}) {
    const qs  = new URLSearchParams(params).toString();
    const url = `${BASE}${path}${qs ? '?' + qs : ''}`;
    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(`API error ${res.status}: ${path}`);
    const json = await res.json();
    return json.data;
  }

  async function post(path, body = {}) {
    const res  = await fetch(`${BASE}${path}`, { method: 'POST', headers, body: JSON.stringify(body) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error?.message || 'Error de API');
    return json.data;
  }

  return { get, post };
}

// ── Componente Principal ───────────────────────────────────────

const HousekeepingBoard = {
  name: 'HousekeepingBoard',

  setup() {
    // Estado
    const loading      = ref(true);
    const rooms        = ref([]);
    const tasks        = ref([]);
    const staff        = ref([]);
    const selectedRoom = ref(null);
    const filter       = ref({ status: 'ALL', floor: 'ALL', staff: 'ALL' });
    const viewMode     = ref('board'); // 'board' | 'list' | 'map'
    const now          = ref(new Date());
    const idProperty   = ref(window.SMART_PROPERTY_ID || 1);
    const apiKey       = ref(window.SMART_API_KEY || '');
    const showTaskModal  = ref(false);
    const activeTask     = ref(null);

    const api = useSmartApi(apiKey.value);

    // ── Datos computados ──────────────────────────────────
    const floors = computed(() => {
      const all = [...new Set(rooms.value.map(r => r.floor))].sort((a, b) => a - b);
      return ['ALL', ...all];
    });

    const filteredRooms = computed(() => {
      return rooms.value.filter(room => {
        if (filter.value.status !== 'ALL' && room.status !== filter.value.status) return false;
        if (filter.value.floor  !== 'ALL' && room.floor  !== filter.value.floor)  return false;
        return true;
      });
    });

    const roomsByFloor = computed(() => {
      const byFloor = {};
      filteredRooms.value.forEach(room => {
        const f = room.floor;
        if (!byFloor[f]) byFloor[f] = [];
        byFloor[f].push(room);
      });
      // Ordenar habitaciones dentro de cada piso
      Object.values(byFloor).forEach(list => list.sort((a, b) => a.room_number - b.room_number));
      return byFloor;
    });

    const stats = computed(() => {
      const all   = rooms.value;
      const total = all.length;
      return {
        total,
        clean:      all.filter(r => ['CL','IN','VC'].includes(r.status)).length,
        dirty:      all.filter(r => ['VD','OD','DI'].includes(r.status)).length,
        in_progress:all.filter(r => r.status === 'IP').length,
        dnd:        all.filter(r => r.status === 'DND').length,
        oo:         all.filter(r => ['OO','OS'].includes(r.status)).length,
        pending_tasks: tasks.value.filter(t => t.status === 'PENDING').length,
        in_progress_tasks: tasks.value.filter(t => t.status === 'IN_PROGRESS').length,
        completed_tasks: tasks.value.filter(t => t.status === 'COMPLETED').length,
      };
    });

    const pendingTasksForSelectedRoom = computed(() => {
      if (!selectedRoom.value) return [];
      return tasks.value.filter(t =>
        t.id_room == selectedRoom.value.id_room &&
        t.status !== 'COMPLETED' && t.status !== 'CANCELLED'
      );
    });

    // ── Métodos ───────────────────────────────────────────
    async function loadData() {
      try {
        loading.value = true;
        const [boardData, tasksData] = await Promise.all([
          api.get('/housekeeping/board', { id_property: idProperty.value }),
          api.get('/housekeeping/tasks', { id_property: idProperty.value, date: todayStr() }),
        ]);
        rooms.value = boardData.rooms || [];
        staff.value = boardData.staff || [];
        tasks.value = tasksData.tasks || [];
      } catch (e) {
        console.error('Error cargando datos:', e);
      } finally {
        loading.value = false;
      }
    }

    function handleWebSocketMessage(msg) {
      switch (msg.type) {
        case 'room_status_change':
          updateRoomStatus(msg.id_room, msg.status);
          break;
        case 'task_update':
          updateTask(msg.task);
          break;
        case 'new_task':
          tasks.value.unshift(msg.task);
          break;
      }
    }

    function updateRoomStatus(idRoom, newStatus) {
      const idx = rooms.value.findIndex(r => r.id_room == idRoom);
      if (idx !== -1) {
        rooms.value[idx] = { ...rooms.value[idx], status: newStatus, updated_at: new Date().toISOString() };
      }
    }

    function updateTask(updatedTask) {
      const idx = tasks.value.findIndex(t => t.id == updatedTask.id);
      if (idx !== -1) {
        tasks.value[idx] = updatedTask;
      } else {
        tasks.value.push(updatedTask);
      }
    }

    function selectRoom(room) {
      selectedRoom.value = selectedRoom.value?.id_room === room.id_room ? null : room;
    }

    function openTaskModal(task) {
      activeTask.value = task;
      showTaskModal.value = true;
    }

    async function startTask(taskId) {
      try {
        const idStaff = getCurrentStaffId();
        await api.post(`/housekeeping/tasks/${taskId}/start`, { id_staff: idStaff });
        const task = tasks.value.find(t => t.id == taskId);
        if (task) {
          task.status     = 'IN_PROGRESS';
          task.started_at = new Date().toISOString();
          task.id_staff   = idStaff;
          updateRoomStatus(task.id_room, 'IP');
        }
        showNotification('Limpieza iniciada', 'success');
      } catch (e) {
        showNotification('Error al iniciar tarea: ' + e.message, 'error');
      }
    }

    async function completeTask(taskId, reportData = {}) {
      try {
        const idStaff = getCurrentStaffId();
        await api.post(`/housekeeping/tasks/${taskId}/complete`, {
          id_staff: idStaff,
          ...reportData,
        });
        const task = tasks.value.find(t => t.id == taskId);
        if (task) {
          task.status       = 'COMPLETED';
          task.completed_at = new Date().toISOString();
          updateRoomStatus(task.id_room, 'VC');
        }
        showTaskModal.value = false;
        showNotification('✓ Habitación marcada como limpia', 'success');
      } catch (e) {
        showNotification('Error: ' + e.message, 'error');
      }
    }

    // ── Helpers ───────────────────────────────────────────
    function getRoomStatusInfo(status) {
      return ROOM_STATUS_LABELS[status] || { label: status, color: '#6b7280', icon: '?' };
    }

    function getTaskForRoom(idRoom) {
      return tasks.value.find(t =>
        t.id_room == idRoom && ['ASSIGNED','IN_PROGRESS'].includes(t.status)
      );
    }

    function getStaffName(idStaff) {
      const s = staff.value.find(s => s.id_staff == idStaff);
      return s ? `${s.firstname} ${s.lastname}` : '—';
    }

    function getCurrentStaffId() {
      return window.SMART_STAFF_ID || 0;
    }

    function todayStr() {
      return new Date().toISOString().slice(0, 10);
    }

    // Notificaciones toast
    const notifications = ref([]);
    function showNotification(msg, type = 'info') {
      const id = Date.now();
      notifications.value.push({ id, msg, type });
      setTimeout(() => {
        notifications.value = notifications.value.filter(n => n.id !== id);
      }, 4000);
    }

    function formatTime(dateStr) {
      if (!dateStr) return '—';
      return new Date(dateStr).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDuration(startedAt) {
      if (!startedAt) return '—';
      const diff = Math.floor((Date.now() - new Date(startedAt).getTime()) / 60000);
      return diff < 60 ? `${diff} min` : `${Math.floor(diff/60)}h ${diff%60}min`;
    }

    // ── Lifecycle ─────────────────────────────────────────
    const ws = useWebSocket(handleWebSocketMessage);
    let pollInterval = null;

    onMounted(async () => {
      await loadData();
      ws.connect();

      // Polling de respaldo cada 30s si WS no está disponible
      pollInterval = setInterval(() => {
        if (!ws.connected.value) loadData();
      }, 30000);

      // Reloj
      setInterval(() => { now.value = new Date(); }, 1000);
    });

    onUnmounted(() => {
      ws.disconnect();
      clearInterval(pollInterval);
    });

    return {
      loading, rooms, tasks, staff, selectedRoom, filter, viewMode, now,
      floors, filteredRooms, roomsByFloor, stats,
      pendingTasksForSelectedRoom, notifications, showTaskModal, activeTask,
      ws,
      ROOM_STATUS_LABELS, TASK_STATUS_LABELS,
      loadData, selectRoom, openTaskModal, startTask, completeTask,
      getRoomStatusInfo, getTaskForRoom, getStaffName,
      formatTime, formatDuration, todayStr,
    };
  },

  template: `
    <div class="smart-hk-board" :class="{ loading }">

      <!-- Notificaciones toast -->
      <div class="smart-notifications">
        <div v-for="n in notifications" :key="n.id"
             class="smart-notification" :class="'smart-notification--' + n.type">
          {{ n.msg }}
        </div>
      </div>

      <!-- Header -->
      <div class="smart-hk-header">
        <div class="smart-hk-header__left">
          <h1 class="smart-hk-title">Panel de Pisos</h1>
          <div class="smart-hk-time">
            {{ now.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) }}
            &nbsp;·&nbsp;
            {{ now.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' }) }}
          </div>
          <div class="smart-ws-indicator" :class="ws.connected.value ? 'connected' : 'disconnected'">
            <span class="smart-ws-dot"></span>
            {{ ws.connected.value ? 'En vivo' : 'Sin conexión en vivo' }}
          </div>
        </div>
        <div class="smart-hk-header__right">
          <button @click="loadData" class="smart-btn smart-btn--ghost">↻ Actualizar</button>
        </div>
      </div>

      <!-- KPI Bar -->
      <div class="smart-kpi-bar">
        <div class="smart-kpi" style="--kpi-color: #22c55e">
          <span class="smart-kpi__num">{{ stats.clean }}</span>
          <span class="smart-kpi__label">Limpias</span>
        </div>
        <div class="smart-kpi" style="--kpi-color: #ef4444">
          <span class="smart-kpi__num">{{ stats.dirty }}</span>
          <span class="smart-kpi__label">Sucias</span>
        </div>
        <div class="smart-kpi" style="--kpi-color: #8b5cf6">
          <span class="smart-kpi__num">{{ stats.in_progress }}</span>
          <span class="smart-kpi__label">En limpieza</span>
        </div>
        <div class="smart-kpi" style="--kpi-color: #6b7280">
          <span class="smart-kpi__num">{{ stats.dnd }}</span>
          <span class="smart-kpi__label">No molestar</span>
        </div>
        <div class="smart-kpi" style="--kpi-color: #f59e0b">
          <span class="smart-kpi__num">{{ stats.pending_tasks }}</span>
          <span class="smart-kpi__label">Tareas pend.</span>
        </div>
        <div class="smart-kpi" style="--kpi-color: #10b981">
          <span class="smart-kpi__num">{{ stats.completed_tasks }}</span>
          <span class="smart-kpi__label">Completadas</span>
        </div>
      </div>

      <!-- Filtros -->
      <div class="smart-hk-filters">
        <div class="smart-filter-group">
          <label>Estado</label>
          <select v-model="filter.status">
            <option value="ALL">Todos</option>
            <option v-for="(info, code) in ROOM_STATUS_LABELS" :key="code" :value="code">
              {{ info.label }}
            </option>
          </select>
        </div>
        <div class="smart-filter-group">
          <label>Piso</label>
          <select v-model="filter.floor">
            <option v-for="f in floors" :key="f" :value="f">
              {{ f === 'ALL' ? 'Todos los pisos' : 'Piso ' + f }}
            </option>
          </select>
        </div>
        <div class="smart-view-toggle">
          <button @click="viewMode = 'board'" :class="{ active: viewMode === 'board' }">⊞ Panel</button>
          <button @click="viewMode = 'list'"  :class="{ active: viewMode === 'list'  }">☰ Lista</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="smart-loading">
        <div class="smart-loading-spinner"></div>
        <span>Cargando estado de pisos...</span>
      </div>

      <!-- Board view: habitaciones agrupadas por piso -->
      <div v-else-if="viewMode === 'board'" class="smart-floors">
        <div v-for="(floorRooms, floor) in roomsByFloor" :key="floor" class="smart-floor">
          <div class="smart-floor-header">
            <span class="smart-floor-num">Piso {{ floor }}</span>
            <span class="smart-floor-stats">
              {{ floorRooms.filter(r => ['CL','IN','VC'].includes(r.status)).length }} limpias ·
              {{ floorRooms.filter(r => ['VD','OD','DI'].includes(r.status)).length }} sucias ·
              {{ floorRooms.filter(r => r.status === 'IP').length }} en limpieza
            </span>
          </div>
          <div class="smart-floor-rooms">
            <div
              v-for="room in floorRooms"
              :key="room.id_room"
              class="smart-room-card"
              :class="[
                'smart-room-card--' + room.status.toLowerCase(),
                { 'smart-room-card--selected': selectedRoom?.id_room === room.id_room },
                { 'smart-room-card--has-task': getTaskForRoom(room.id_room) }
              ]"
              @click="selectRoom(room)"
            >
              <div class="smart-room-card__header">
                <span class="smart-room-num">{{ room.room_number }}</span>
                <span class="smart-room-type">{{ room.room_type_short || '' }}</span>
              </div>
              <div class="smart-room-status-icon"
                   :style="{ color: getRoomStatusInfo(room.status).color }">
                {{ getRoomStatusInfo(room.status).icon }}
              </div>
              <div class="smart-room-status-label"
                   :style="{ color: getRoomStatusInfo(room.status).color }">
                {{ getRoomStatusInfo(room.status).label }}
              </div>
              <div v-if="getTaskForRoom(room.id_room)" class="smart-room-staff">
                {{ getStaffName(getTaskForRoom(room.id_room)?.id_staff) }}
              </div>
              <div v-if="room.status === 'IP'" class="smart-room-duration">
                {{ formatDuration(getTaskForRoom(room.id_room)?.started_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- List view: tabla de tareas pendientes -->
      <div v-else-if="viewMode === 'list'" class="smart-task-list">
        <table class="smart-table">
          <thead>
            <tr>
              <th>Habitación</th>
              <th>Tipo de tarea</th>
              <th>Estado</th>
              <th>Camarera</th>
              <th>Inicio</th>
              <th>Duración</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="task in tasks" :key="task.id"
                :class="'smart-table-row--' + task.status.toLowerCase()">
              <td><strong>{{ task.room_number }}</strong></td>
              <td>{{ task.task_type_label || task.task_type }}</td>
              <td>
                <span class="smart-badge"
                      :style="{ background: TASK_STATUS_LABELS[task.status]?.color + '22',
                                color: TASK_STATUS_LABELS[task.status]?.color }">
                  {{ TASK_STATUS_LABELS[task.status]?.label || task.status }}
                </span>
              </td>
              <td>{{ getStaffName(task.id_staff) }}</td>
              <td>{{ formatTime(task.started_at) }}</td>
              <td>{{ task.status === 'IN_PROGRESS' ? formatDuration(task.started_at) : (task.duration_minutes ? task.duration_minutes + 'min' : '—') }}</td>
              <td>
                <button v-if="task.status === 'ASSIGNED'" @click="startTask(task.id)"
                        class="smart-btn smart-btn--sm smart-btn--primary">Iniciar</button>
                <button v-if="task.status === 'IN_PROGRESS'" @click="openTaskModal(task)"
                        class="smart-btn smart-btn--sm smart-btn--success">Completar</button>
              </td>
            </tr>
            <tr v-if="tasks.length === 0">
              <td colspan="7" style="text-align:center; padding:2rem; color:#6b7280;">
                No hay tareas para hoy
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Panel lateral: detalle de habitación seleccionada -->
      <transition name="slide-in">
        <div v-if="selectedRoom" class="smart-room-detail">
          <div class="smart-room-detail__header">
            <h3>Habitación {{ selectedRoom.room_number }}</h3>
            <button @click="selectedRoom = null" class="smart-btn smart-btn--ghost smart-btn--sm">✕</button>
          </div>
          <div class="smart-room-detail__status"
               :style="{ color: getRoomStatusInfo(selectedRoom.status).color }">
            {{ getRoomStatusInfo(selectedRoom.status).label }}
          </div>
          <div class="smart-room-detail__info">
            <div><span>Tipo:</span> {{ selectedRoom.room_type || '—' }}</div>
            <div><span>Piso:</span> {{ selectedRoom.floor }}</div>
            <div><span>Actualizado:</span> {{ formatTime(selectedRoom.updated_at) }}</div>
          </div>
          <div class="smart-room-detail__tasks">
            <h4>Tareas activas</h4>
            <div v-if="pendingTasksForSelectedRoom.length === 0" class="smart-empty">
              Sin tareas activas
            </div>
            <div v-for="task in pendingTasksForSelectedRoom" :key="task.id"
                 class="smart-task-item">
              <div class="smart-task-item__type">{{ task.task_type }}</div>
              <div class="smart-task-item__staff">{{ getStaffName(task.id_staff) }}</div>
              <div class="smart-task-item__actions">
                <button v-if="task.status === 'PENDING' || task.status === 'ASSIGNED'"
                        @click="startTask(task.id)"
                        class="smart-btn smart-btn--sm smart-btn--primary">
                  ▶ Iniciar
                </button>
                <button v-if="task.status === 'IN_PROGRESS'"
                        @click="openTaskModal(task)"
                        class="smart-btn smart-btn--sm smart-btn--success">
                  ✓ Completar
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>

      <!-- Modal: completar tarea -->
      <transition name="fade">
        <div v-if="showTaskModal && activeTask" class="smart-modal-overlay" @click.self="showTaskModal = false">
          <div class="smart-modal">
            <div class="smart-modal__header">
              <h3>Completar Limpieza — Hab. {{ activeTask.room_number }}</h3>
              <button @click="showTaskModal = false">✕</button>
            </div>
            <div class="smart-modal__body">
              <CompleteTaskForm
                :task="activeTask"
                @complete="(data) => completeTask(activeTask.id, data)"
                @cancel="showTaskModal = false"
              />
            </div>
          </div>
        </div>
      </transition>
    </div>
  `,
};

// ── Sub-componente: Formulario de completar tarea ─────────────

const CompleteTaskForm = {
  name: 'CompleteTaskForm',
  props: ['task'],
  emits: ['complete', 'cancel'],
  setup(props, { emit }) {
    const notes     = ref('');
    const lostItems = ref([]);
    const checklist = ref({
      beds_made:         false,
      bathroom_clean:    false,
      floor_vacuumed:    false,
      amenities_replaced:false,
      minibar_checked:   false,
      windows_cleaned:   false,
    });

    function addLostItem() {
      lostItems.value.push({ description: '', location: '', category: 'OTHER' });
    }
    function removeLostItem(idx) {
      lostItems.value.splice(idx, 1);
    }
    function submit() {
      emit('complete', {
        notes:      notes.value,
        checklist:  checklist.value,
        lost_items: lostItems.value.filter(i => i.description.trim()),
      });
    }

    return { notes, lostItems, checklist, addLostItem, removeLostItem, submit };
  },
  template: `
    <form @submit.prevent="submit" class="smart-form">
      <fieldset>
        <legend>Lista de verificación</legend>
        <label v-for="(val, key) in checklist" :key="key" class="smart-checkbox">
          <input type="checkbox" v-model="checklist[key]">
          <span>{{ {
            beds_made: 'Camas tendidas', bathroom_clean: 'Baño limpio',
            floor_vacuumed: 'Piso aspirado', amenities_replaced: 'Amenidades repuestas',
            minibar_checked: 'Minibar verificado', windows_cleaned: 'Vidrios limpios'
          }[key] }}</span>
        </label>
      </fieldset>

      <div class="smart-form-group">
        <label>Notas (opcional)</label>
        <textarea v-model="notes" rows="2" placeholder="Observaciones, daños, solicitudes..."></textarea>
      </div>

      <div class="smart-form-group">
        <div class="smart-form-header">
          <label>Objetos encontrados</label>
          <button type="button" @click="addLostItem" class="smart-btn smart-btn--xs">+ Añadir</button>
        </div>
        <div v-for="(item, i) in lostItems" :key="i" class="smart-lost-item">
          <input v-model="item.description" placeholder="Descripción del objeto">
          <input v-model="item.location"    placeholder="Ubicación (ej: bajo la cama)">
          <button type="button" @click="removeLostItem(i)" class="smart-btn smart-btn--xs smart-btn--danger">✕</button>
        </div>
      </div>

      <div class="smart-form-actions">
        <button type="button" @click="$emit('cancel')" class="smart-btn smart-btn--ghost">Cancelar</button>
        <button type="submit" class="smart-btn smart-btn--success">✓ Marcar como limpia</button>
      </div>
    </form>
  `,
};

// ── Inicialización ─────────────────────────────────────────────

export function initHousekeepingBoard(mountSelector = '#smart-hk-board') {
  const app = createApp(HousekeepingBoard);
  app.component('CompleteTaskForm', CompleteTaskForm);
  app.mount(mountSelector);
  return app;
}

// Auto-init si el elemento existe en la página
if (document.getElementById('smart-hk-board')) {
  initHousekeepingBoard();
}
