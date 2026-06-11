{*
 * SmartHotelOS — Huéspedes en Casa
 * Grupo Smart de Administración
 *}
<div class="smart-admin-page">

  <div class="smart-panel-header">
    <div class="smart-panel-header__left">
      <h2 class="smart-panel-title">
        <span class="smart-panel-title__icon">🏠</span>
        {l s='Huéspedes en Casa' mod='smart_frontdesk'}
        <span class="smart-panel-title__date">{$smart_today|date_format:'%d/%m/%Y'}</span>
      </h2>
    </div>
    <div class="smart-panel-header__kpis">
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--gold">{$smart_total}</span>
        <span class="smart-mini-kpi__label">{l s='En casa' mod='smart_frontdesk'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num" style="color:#C9A84C">{$smart_vip}</span>
        <span class="smart-mini-kpi__label">{l s='VIP' mod='smart_frontdesk'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--amber">{$smart_checkout_today}</span>
        <span class="smart-mini-kpi__label">{l s='Salen hoy' mod='smart_frontdesk'}</span>
      </div>
      {if $smart_occasions|count > 0}
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num" style="color:#C9A84C">{$smart_occasions|count}</span>
        <span class="smart-mini-kpi__label">{l s='Celebraciones' mod='smart_frontdesk'}</span>
      </div>
      {/if}
    </div>
    <div class="smart-panel-header__actions">
      <button onclick="window.location.reload()" class="smart-btn smart-btn--ghost smart-btn--sm">
        ↻ {l s='Actualizar' mod='smart_frontdesk'}
      </button>
    </div>
  </div>

  {* ── Ocasiones especiales ── *}
  {if $smart_occasions|count > 0}
  <div class="smart-occasions-banner">
    <span class="smart-occasions-banner__icon">🎉</span>
    <strong>{l s='Celebraciones hoy:' mod='smart_frontdesk'}</strong>
    {foreach from=$smart_occasions item=occ}
      <span class="smart-occasion-chip">
        Hab. {$occ.room_number} — {$occ.firstname} {$occ.lastname}:
        <em>{$occ.occasion_type}</em>
      </span>
    {/foreach}
  </div>
  {/if}

  {* ── Filtros ── *}
  <div class="smart-filter-bar">
    <input type="text" id="smart-search"
           placeholder="{l s='Buscar huésped, habitación...' mod='smart_frontdesk'}"
           class="smart-search-input" oninput="filterGuests(this.value)">
    <div class="smart-filter-tabs">
      <button class="smart-filter-tab active" onclick="filterBy('all',this)">{l s='Todos' mod='smart_frontdesk'}</button>
      <button class="smart-filter-tab" onclick="filterBy('vip',this)">★ VIP</button>
      <button class="smart-filter-tab" onclick="filterBy('checkout',this)">{l s='Salen hoy' mod='smart_frontdesk'}</button>
      <button class="smart-filter-tab" onclick="filterBy('issues',this)">⚠ {l s='Con incidencias' mod='smart_frontdesk'}</button>
    </div>
  </div>

  {* ── Tabla de huéspedes ── *}
  {if $smart_in_house|count > 0}
  <div class="smart-table-wrap">
    <table class="smart-table" id="smart-inhouse-table">
      <thead>
        <tr>
          <th>{l s='Habitación' mod='smart_frontdesk'}</th>
          <th>{l s='Huésped' mod='smart_frontdesk'}</th>
          <th>{l s='Tipo' mod='smart_frontdesk'}</th>
          <th>{l s='Llegó' mod='smart_frontdesk'}</th>
          <th>{l s='Sale' mod='smart_frontdesk'}</th>
          <th>{l s='Noches' mod='smart_frontdesk'}</th>
          <th>{l s='Estado hab.' mod='smart_frontdesk'}</th>
          <th>{l s='Cargos' mod='smart_frontdesk'}</th>
          <th>{l s='Acciones' mod='smart_frontdesk'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$smart_in_house item=g}
          {assign var=is_checkout value=($g.nights_remaining == 0)}
          {assign var=has_issue   value=($g.open_tickets > 0)}
          <tr class="smart-inhouse-row
               {if $g.vip_level > 0}smart-inhouse-row--vip{/if}
               {if $is_checkout}smart-inhouse-row--checkout{/if}
               {if $has_issue}smart-inhouse-row--issue{/if}"
              data-name="{$g.firstname|lower} {$g.lastname|lower}"
              data-room="{$g.room_number|lower}"
              data-filter="{if $g.vip_level > 0}vip {/if}{if $is_checkout}checkout {/if}{if $has_issue}issues{/if}">

            <td>
              <span class="smart-room-badge">{$g.room_number}</span>
            </td>
            <td>
              <div class="smart-guest-cell">
                {if $g.vip_level > 0}
                  <span class="smart-vip-star" title="VIP nivel {$g.vip_level}">
                    {section name=v loop=$g.vip_level}★{/section}
                  </span>
                {/if}
                <div>
                  <strong>{$g.firstname} {$g.lastname}</strong>
                  {if $g.guest_code}
                    <span class="smart-guest-code-sm">{$g.guest_code}</span>
                  {/if}
                  {if $g.total_stays > 1}
                    <small class="smart-return-badge">↩ {$g.total_stays} {l s='visitas' mod='smart_frontdesk'}</small>
                  {/if}
                </div>
              </div>
            </td>
            <td><span class="smart-text-dim">{$g.room_type_name|truncate:20}</span></td>
            <td>{$g.booking_date_from|date_format:'%d/%m'}</td>
            <td class="{if $is_checkout}text-amber{/if}">
              {$g.booking_date_to|date_format:'%d/%m'}
              {if $is_checkout}<span class="smart-badge-today">{l s='HOY' mod='smart_frontdesk'}</span>{/if}
            </td>
            <td class="smart-center">
              <span>{$g.nights_stayed}</span>
              <span class="smart-text-dim"> / {$g.nights_stayed + $g.nights_remaining}</span>
            </td>
            <td>
              <span class="smart-hk-status-dot" data-status="{$g.room_hk_status|default:'VD'}"></span>
              <span class="smart-text-dim" style="font-size:0.7rem">{$g.room_hk_status|default:'—'}</span>
            </td>
            <td>
              {if $g.total_charges > 0}
                <span class="text-gold">
                  ${$g.total_charges|number_format:0:',':'.'}
                </span>
              {else}
                <span class="smart-text-dim">—</span>
              {/if}
            </td>
            <td>
              <div class="smart-row-actions">
                <button onclick="openGuestProfile({$g.id_customer})"
                        class="smart-btn smart-btn--ghost smart-btn--xs" title="Ver perfil">
                  👤
                </button>
                {if $has_issue}
                  <button onclick="openMaintTickets({$g.id_room})"
                          class="smart-btn smart-btn--danger smart-btn--xs" title="Ver incidencias">
                    🔧
                  </button>
                {/if}
                {if $is_checkout}
                  <a href="{$link->getAdminLink('AdminSmartDepartures')|escape:'html'}"
                     class="smart-btn smart-btn--primary smart-btn--xs">
                    {l s='Check-out' mod='smart_frontdesk'}
                  </a>
                {/if}
                <button onclick="sendMessage('{$g.phone_mobile|escape:'javascript'}')"
                        class="smart-btn smart-btn--ghost smart-btn--xs" title="WhatsApp">
                  💬
                </button>
              </div>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  </div>
  {else}
  <div class="smart-empty-state">
    <div class="smart-empty-state__icon">🏨</div>
    <h3>{l s='Sin huéspedes en casa' mod='smart_frontdesk'}</h3>
    <p>{l s='No hay reservas activas en este momento.' mod='smart_frontdesk'}</p>
  </div>
  {/if}
</div>

<style>
.smart-occasions-banner {
  display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;
  padding:0.85rem 1.25rem; background:rgba(201,168,76,0.08);
  border:1px solid rgba(201,168,76,0.2); border-radius:4px; margin-bottom:1.25rem;
  font-size:0.82rem;
}
.smart-occasions-banner__icon { font-size:1.1rem; }
.smart-occasion-chip {
  padding:0.25rem 0.75rem; background:rgba(201,168,76,0.15);
  border:1px solid rgba(201,168,76,0.3); border-radius:100px;
  font-size:0.72rem; color:#E8C97A;
}
.smart-table-wrap { overflow-x:auto; }
.smart-inhouse-row--vip td:first-child { border-left:2px solid #C9A84C; }
.smart-inhouse-row--checkout td:nth-child(5) { font-weight:500; }
.smart-inhouse-row--issue { opacity:0.9; }
.smart-room-badge {
  display:inline-block; padding:0.2rem 0.6rem; background:rgba(201,168,76,0.1);
  border:1px solid rgba(201,168,76,0.3); color:#C9A84C;
  font-family:monospace; font-size:0.82rem; font-weight:600; letter-spacing:0.05em;
}
.smart-guest-cell { display:flex; align-items:flex-start; gap:0.5rem; }
.smart-vip-star { color:#C9A84C; font-size:0.7rem; margin-top:3px; white-space:nowrap; }
.smart-guest-code-sm {
  display:inline-block; font-family:monospace; font-size:0.6rem; color:#C9A84C;
  background:rgba(201,168,76,0.1); padding:1px 4px; margin-left:4px; border-radius:2px;
}
.smart-return-badge { font-size:0.6rem; color:#6B6560; display:block; }
.smart-text-dim { color:#6B6560; }
.smart-center { text-align:center; }
.smart-badge-today {
  display:inline-block; padding:0.1rem 0.4rem;
  background:rgba(245,158,11,0.2); color:#f59e0b;
  font-size:0.55rem; font-weight:700; letter-spacing:0.15em;
  margin-left:4px; vertical-align:middle;
}
.smart-hk-status-dot {
  display:inline-block; width:8px; height:8px; border-radius:50%;
  margin-right:4px; vertical-align:middle;
  background:#6B6560;
}
[data-status="CL"] .smart-hk-status-dot,
.smart-hk-status-dot[data-status="CL"] { background:#22c55e; }
.smart-hk-status-dot[data-status="IN"] { background:#10b981; }
.smart-hk-status-dot[data-status="IP"] { background:#8b5cf6; }
.smart-hk-status-dot[data-status="VD"],
.smart-hk-status-dot[data-status="OD"] { background:#f59e0b; }
.smart-hk-status-dot[data-status="DND"] { background:#6b7280; }
.smart-row-actions { display:flex; gap:0.35rem; align-items:center; }
</style>

<script>
function filterGuests(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.smart-inhouse-row').forEach(row => {
    const name = row.dataset.name || '';
    const room = row.dataset.room || '';
    row.style.display = (name.includes(q) || room.includes(q)) ? '' : 'none';
  });
}
function filterBy(type, btn) {
  document.querySelectorAll('.smart-filter-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.smart-inhouse-row').forEach(row => {
    if (type === 'all') { row.style.display = ''; return; }
    row.style.display = (row.dataset.filter || '').includes(type) ? '' : 'none';
  });
}
function openGuestProfile(customerId) {
  window.location.href = '{$link->getAdminLink('AdminSmartGuestList')|escape:'javascript'}' + '&id_customer=' + customerId;
}
function openMaintTickets(roomId) {
  window.location.href = '{$link->getAdminLink('AdminSmartMaintTickets')|escape:'javascript'}' + '&id_room=' + roomId;
}
function sendMessage(phone) {
  if (phone) { window.open('https://wa.me/' + phone.replace(/[^0-9]/g,''), '_blank'); }
  else { alert('Sin número WhatsApp registrado'); }
}
setTimeout(() => window.location.reload(), 120000);
</script>
