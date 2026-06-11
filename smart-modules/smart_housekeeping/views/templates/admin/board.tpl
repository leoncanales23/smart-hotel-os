{*
 * SmartHotelOS — Panel de Pisos (Housekeeping Board)
 * Grupo Smart de Administración
 *}

<div class="smart-admin-page" id="smart-hk-board-page">
  <link rel="stylesheet"
        href="{$smarty.const._MODULE_DIR_}smart_housekeeping/../../../smart-themes/smart-luxury/css/admin.css">

  {* ── Header ── *}
  <div class="smart-panel-header">
    <div class="smart-panel-header__left">
      <h2 class="smart-panel-title">
        <span class="smart-panel-title__icon">🛏</span>
        {l s='Panel de Pisos' mod='smart_housekeeping'}
        <span class="smart-panel-title__date">{$smart_today|date_format:'%d/%m/%Y'}</span>
      </h2>
    </div>
    <div class="smart-panel-header__kpis">
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--green">
          {($smart_status_counts.CL|default:0) + ($smart_status_counts.IN|default:0) + ($smart_status_counts.VC|default:0)}
        </span>
        <span class="smart-mini-kpi__label">{l s='Limpias' mod='smart_housekeeping'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--amber">
          {($smart_status_counts.VD|default:0) + ($smart_status_counts.OD|default:0)}
        </span>
        <span class="smart-mini-kpi__label">{l s='Sucias' mod='smart_housekeeping'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--gold">
          {$smart_status_counts.IP|default:0}
        </span>
        <span class="smart-mini-kpi__label">{l s='En limpieza' mod='smart_housekeeping'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num" style="color:#6b7280">
          {$smart_status_counts.DND|default:0}
        </span>
        <span class="smart-mini-kpi__label">{l s='No Molestar' mod='smart_housekeeping'}</span>
      </div>
    </div>
    <div class="smart-panel-header__actions">
      <a href="{$link->getAdminLink('AdminSmartHkBoard')|escape:'html'}&generate_plan=1&token={$smart_token}"
         class="smart-btn smart-btn--primary smart-btn--sm">
        ⊕ {l s='Generar Plan del Día' mod='smart_housekeeping'}
      </a>
      <button onclick="window.location.reload()" class="smart-btn smart-btn--ghost smart-btn--sm">
        ↻ {l s='Actualizar' mod='smart_housekeeping'}
      </button>
    </div>
  </div>

  {* ── Staff del día ── *}
  {if $smart_staff|count > 0}
  <div class="smart-staff-bar">
    <span class="smart-staff-bar__label">{l s='Staff activo:' mod='smart_housekeeping'}</span>
    {foreach from=$smart_staff item=s}
      <div class="smart-staff-chip"
           title="{$s.tasks_assigned} asignadas · {$s.tasks_done} completadas">
        <span class="smart-staff-chip__role smart-staff-chip__role--{$s.role}"></span>
        <span class="smart-staff-chip__name">{$s.firstname}</span>
        <span class="smart-staff-chip__count">{$s.tasks_done}/{$s.tasks_assigned}</span>
      </div>
    {/foreach}
  </div>
  {/if}

  {* ── Pisos ── *}
  <div class="smart-floors">
    {foreach from=$smart_rooms_by_floor key=floor item=rooms}
      {assign var=floor_clean  value=0}
      {assign var=floor_dirty  value=0}
      {assign var=floor_inprog value=0}
      {foreach from=$rooms item=r}
        {if $r.hk_status == 'CL' || $r.hk_status == 'IN' || $r.hk_status == 'VC'}
          {assign var=floor_clean value=$floor_clean+1}
        {elseif $r.hk_status == 'IP'}
          {assign var=floor_inprog value=$floor_inprog+1}
        {else}
          {assign var=floor_dirty value=$floor_dirty+1}
        {/if}
      {/foreach}

      <div class="smart-floor">
        <div class="smart-floor-header">
          <span class="smart-floor-num">{l s='Piso' mod='smart_housekeeping'} {$floor}</span>
          <span class="smart-floor-stats">
            <span style="color:#22c55e">{$floor_clean} {l s='limpias' mod='smart_housekeeping'}</span>
            &nbsp;·&nbsp;
            <span style="color:#f59e0b">{$floor_dirty} {l s='sucias' mod='smart_housekeeping'}</span>
            {if $floor_inprog > 0}
              &nbsp;·&nbsp;
              <span style="color:#8b5cf6">{$floor_inprog} {l s='en limpieza' mod='smart_housekeeping'}</span>
            {/if}
          </span>
        </div>
        <div class="smart-floor-rooms">
          {foreach from=$rooms item=room}
            {assign var=status_info value=$smart_status_labels[$room.hk_status]|default:[0=>'?',1=>'#888']}
            <div class="smart-room-card
                 {if $room.task_status == 'IN_PROGRESS'} smart-room-card--inprog{/if}
                 {if $room.hk_status == 'DND'} smart-room-card--dnd{/if}
                 {if $room.hk_status == 'OO' || $room.hk_status == 'OS'} smart-room-card--oos{/if}"
                 data-room-id="{$room.id_room}"
                 data-status="{$room.hk_status}"
                 onclick="openRoomDetail({$room.id_room|intval}, '{$room.room_num|escape:'javascript'}', '{$room.hk_status|escape:'javascript'}')">
              <div class="smart-room-card__header">
                <span class="smart-room-num">{$room.room_num}</span>
              </div>
              <span class="smart-room-status-icon" style="color:{$status_info[1]}">
                {if $room.hk_status == 'CL' || $room.hk_status == 'IN'}✓
                {elseif $room.hk_status == 'IP'}⟳
                {elseif $room.hk_status == 'DND'}⊘
                {elseif $room.hk_status == 'OO' || $room.hk_status == 'OS'}✕
                {else}○{/if}
              </span>
              <span class="smart-room-status-label" style="color:{$status_info[1]}">{$status_info[0]}</span>
              {if $room.staff_name}
                <span class="smart-room-staff">{$room.staff_name|truncate:12}</span>
              {/if}
              {if $room.task_status == 'IN_PROGRESS' && $room.started_at}
                <span class="smart-room-duration" id="dur-{$room.id_room}"
                      data-started="{$room.started_at}">—</span>
              {/if}
              {if $room.id_task && $room.task_status != 'IN_PROGRESS'}
                <div class="smart-room-task-dot" title="{$room.task_type}"></div>
              {/if}
            </div>
          {/foreach}
        </div>
      </div>
    {/foreach}
  </div>

  {* ── Leyenda ── *}
  <div class="smart-legend">
    {foreach from=$smart_status_labels key=code item=info}
      <div class="smart-legend-item">
        <span class="smart-legend-dot" style="background:{$info[1]}"></span>
        <span class="smart-legend-label">{$info[0]}</span>
      </div>
    {/foreach}
  </div>
</div>

{* ── Modal detalle habitación ── *}
<div id="smart-room-modal" class="smart-modal-overlay" style="display:none"
     onclick="if(event.target===this)closeRoomModal()">
  <div class="smart-modal">
    <div class="smart-modal__header">
      <h3 id="smart-room-modal-title">{l s='Habitación' mod='smart_housekeeping'}</h3>
      <button onclick="closeRoomModal()" class="smart-modal__close">✕</button>
    </div>
    <div class="smart-modal__body" id="smart-room-modal-body">
      <div class="smart-form-row">
        <div class="smart-form-group">
          <label>{l s='Cambiar estado' mod='smart_housekeeping'}</label>
          <select id="room-new-status" class="smart-select">
            {foreach from=$smart_status_labels key=code item=info}
              <option value="{$code}">{$info[0]}</option>
            {/foreach}
          </select>
        </div>
      </div>
    </div>
    <div class="smart-modal__footer">
      <button onclick="closeRoomModal()" class="smart-btn smart-btn--ghost">
        {l s='Cancelar' mod='smart_housekeeping'}
      </button>
      <button onclick="updateRoomStatus()" class="smart-btn smart-btn--primary">
        {l s='Actualizar Estado' mod='smart_housekeeping'}
      </button>
    </div>
  </div>
</div>

<style>
.smart-staff-bar {
  display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;
  padding:0.75rem 1rem; background:var(--sg-surface,#16161A);
  border:1px solid var(--sg-border-dim,rgba(255,255,255,0.07));
  border-radius:4px; flex-wrap:wrap;
}
.smart-staff-bar__label {
  font-size:0.62rem; letter-spacing:0.2em; text-transform:uppercase;
  color:var(--sg-text-muted,#6B6560);
}
.smart-staff-chip {
  display:inline-flex; align-items:center; gap:0.35rem;
  padding:0.25rem 0.6rem;
  background:var(--sg-surface-2,#1E1E24);
  border:1px solid var(--sg-border-dim,rgba(255,255,255,0.07));
  border-radius:100px; cursor:default;
}
.smart-staff-chip__role {
  width:6px; height:6px; border-radius:50%;
  background:var(--sg-text-muted,#6B6560);
}
.smart-staff-chip__role--supervisor { background:#C9A84C; }
.smart-staff-chip__role--inspector  { background:#22c55e; }
.smart-staff-chip__name  { font-size:0.72rem; color:var(--sg-text,#F0EDE4); }
.smart-staff-chip__count { font-size:0.62rem; color:var(--sg-text-muted,#6B6560); font-family:monospace; }
.smart-room-task-dot {
  width:4px; height:4px; background:#C9A84C; border-radius:50%;
  position:absolute; top:4px; right:4px;
}
.smart-room-card { position:relative; }
.smart-room-card--dnd   { opacity:0.6; }
.smart-room-card--oos   { opacity:0.5; border-style:dashed !important; }
.smart-room-card--inprog{ animation:hk-pulse 2s ease-in-out infinite; }
@keyframes hk-pulse {
  0%,100% { border-color:rgba(139,92,246,0.3); }
  50%      { border-color:rgba(139,92,246,0.8); }
}
.smart-legend {
  display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:1.5rem;
  padding:0.75rem 1rem;
  border:1px solid var(--sg-border-dim,rgba(255,255,255,0.07));
  border-radius:4px;
}
.smart-legend-item { display:flex; align-items:center; gap:0.35rem; }
.smart-legend-dot  { width:8px; height:8px; border-radius:50%; }
.smart-legend-label{ font-size:0.65rem; color:var(--sg-text-muted,#6B6560); }
</style>

<script>
let currentRoomId = null;

function openRoomDetail(roomId, roomNum, currentStatus) {
  currentRoomId = roomId;
  document.getElementById('smart-room-modal-title').textContent =
    '{l s="Habitación" mod="smart_housekeeping"} ' + roomNum;
  document.getElementById('room-new-status').value = currentStatus;
  document.getElementById('smart-room-modal').style.display = 'flex';
}
function closeRoomModal() {
  document.getElementById('smart-room-modal').style.display = 'none';
  currentRoomId = null;
}
function updateRoomStatus() {
  if (!currentRoomId) return;
  const newStatus = document.getElementById('room-new-status').value;
  fetch('{$link->getAdminLink('AdminSmartHkBoard')|escape:'javascript'}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      ajax: 1, action: 'UpdateRoomStatus',
      id_room: currentRoomId, status: newStatus,
      token: '{$smart_token|escape:"javascript"}'
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const card = document.querySelector('[data-room-id="' + currentRoomId + '"]');
      if (card) { card.dataset.status = newStatus; location.reload(); }
    }
    closeRoomModal();
  });
}

/* Contadores de duración en vivo */
function updateDurations() {
  document.querySelectorAll('[data-started]').forEach(el => {
    const started = new Date(el.dataset.started.replace(' ', 'T'));
    const diff    = Math.floor((Date.now() - started.getTime()) / 60000);
    el.textContent = diff < 60 ? diff + 'min' : Math.floor(diff/60) + 'h' + (diff%60) + 'm';
  });
}
updateDurations();
setInterval(updateDurations, 60000);

/* Auto-refresh cada 90 segundos */
setTimeout(() => location.reload(), 90000);
</script>
