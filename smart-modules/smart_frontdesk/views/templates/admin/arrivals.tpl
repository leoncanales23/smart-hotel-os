{*
 * SmartHotelOS — Panel de Llegadas del Día
 * Grupo Smart de Administración
 * Vista Twig para el panel de llegadas del Front Desk
 *}

<div class="smart-admin-page">

  {* ── Header del panel ── *}
  <div class="smart-panel-header">
    <div class="smart-panel-header__left">
      <h2 class="smart-panel-title">
        <span class="smart-panel-title__icon">✈</span>
        {l s='Llegadas del Día' mod='smart_frontdesk'}
        <span class="smart-panel-title__date">{$smarty.now|date_format:'%A %d de %B, %Y'}</span>
      </h2>
    </div>
    <div class="smart-panel-header__kpis">
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--gold">{$smart_arrivals_count}</span>
        <span class="smart-mini-kpi__label">{l s='Llegadas totales' mod='smart_frontdesk'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--green">{$smart_checkedin_count}</span>
        <span class="smart-mini-kpi__label">{l s='Check-in realizado' mod='smart_frontdesk'}</span>
      </div>
      <div class="smart-mini-kpi">
        <span class="smart-mini-kpi__num smart-mini-kpi__num--amber">
          {$smart_arrivals_count - $smart_checkedin_count}
        </span>
        <span class="smart-mini-kpi__label">{l s='Pendientes' mod='smart_frontdesk'}</span>
      </div>
    </div>
    <div class="smart-panel-header__actions">
      <button onclick="window.location.reload()" class="smart-btn smart-btn--ghost">
        ↻ {l s='Actualizar' mod='smart_frontdesk'}
      </button>
      <button onclick="window.print()" class="smart-btn smart-btn--ghost">
        ⎙ {l s='Imprimir' mod='smart_frontdesk'}
      </button>
    </div>
  </div>

  {* ── Filtro rápido ── *}
  <div class="smart-filter-bar">
    <input type="text" id="smart-search" placeholder="{l s='Buscar huésped, reserva...' mod='smart_frontdesk'}"
           class="smart-search-input" oninput="filterArrivals(this.value)">
    <div class="smart-filter-tabs">
      <button class="smart-filter-tab active" onclick="filterByStatus('all', this)">
        {l s='Todos' mod='smart_frontdesk'}
      </button>
      <button class="smart-filter-tab" onclick="filterByStatus('pending', this)">
        {l s='Pendientes' mod='smart_frontdesk'}
      </button>
      <button class="smart-filter-tab" onclick="filterByStatus('done', this)">
        {l s='Check-in OK' mod='smart_frontdesk'}
      </button>
      <button class="smart-filter-tab" onclick="filterByStatus('vip', this)">
        ★ {l s='VIP' mod='smart_frontdesk'}
      </button>
    </div>
  </div>

  {* ── Lista de llegadas ── *}
  {if $smart_arrivals|count > 0}
    <div class="smart-arrivals-grid" id="smart-arrivals-list">
      {foreach from=$smart_arrivals item=arrival}
        <div class="smart-arrival-card
             {if $arrival.booking_status == 2}smart-arrival-card--done{/if}
             {if $arrival.vip_level > 0}smart-arrival-card--vip{/if}"
             data-name="{$arrival.firstname|lower} {$arrival.lastname|lower}"
             data-booking="{$arrival.id}"
             data-status="{if $arrival.booking_status == 2}done{else}pending{/if}"
             data-vip="{if $arrival.vip_level > 0}vip{else}std{/if}">

          {* Cabecera de la tarjeta *}
          <div class="smart-arrival-card__header">
            <div class="smart-arrival-card__guest">
              {if $arrival.vip_level > 0}
                <span class="smart-vip-badge">
                  {section name=s loop=$arrival.vip_level}★{/section}
                  VIP
                </span>
              {/if}
              <h3 class="smart-arrival-card__name">
                {$arrival.firstname} {$arrival.lastname}
              </h3>
              {if $arrival.guest_code}
                <span class="smart-guest-code">{$arrival.guest_code}</span>
              {/if}
              {if $arrival.total_stays > 0}
                <span class="smart-returning-badge">
                  ↩ {$arrival.total_stays} {l s='estancias previas' mod='smart_frontdesk'}
                </span>
              {/if}
            </div>
            <div class="smart-arrival-card__status">
              {if $arrival.booking_status == 2}
                <span class="smart-status-badge smart-status-badge--success">
                  ✓ {l s='Check-in Realizado' mod='smart_frontdesk'}
                </span>
              {else}
                <span class="smart-status-badge smart-status-badge--pending">
                  ○ {l s='Pendiente' mod='smart_frontdesk'}
                </span>
              {/if}
            </div>
          </div>

          {* Detalles de la reserva *}
          <div class="smart-arrival-card__body">
            <div class="smart-arrival-details">
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Reserva' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">#REF-{$arrival.id|string_format:'%06d'}</span>
              </div>
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Tipo hab.' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">{$arrival.room_type_name}</span>
              </div>
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Check-in' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">{$arrival.booking_date_from|date_format:'%d/%m %H:%M'}</span>
              </div>
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Check-out' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">{$arrival.booking_date_to|date_format:'%d/%m'}</span>
              </div>
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Noches' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">
                  {math equation="(strtotime(b) - strtotime(a)) / 86400"
                        a=$arrival.booking_date_from b=$arrival.booking_date_to}
                </span>
              </div>
              <div class="smart-detail-item">
                <span class="smart-detail-label">{l s='Habitación' mod='smart_frontdesk'}</span>
                <span class="smart-detail-val">
                  {if $arrival.assigned_room}
                    <strong class="smart-room-num">{$arrival.assigned_room}</strong>
                  {else}
                    <em class="smart-not-assigned">{l s='Sin asignar' mod='smart_frontdesk'}</em>
                  {/if}
                </span>
              </div>
            </div>

            {* Alertas de perfil: alergias y ocasiones *}
            {if $arrival.allergies|count > 0}
              <div class="smart-alert-row smart-alert-row--danger">
                <span class="smart-alert-icon">⚠</span>
                <strong>{l s='ALERGIAS:' mod='smart_frontdesk'}</strong>
                {foreach from=$arrival.allergies item=allergy name=al}
                  <span class="smart-allergy-tag smart-allergy-tag--{$allergy.severity}">
                    {$allergy.allergy_type}
                  </span>
                  {if !$smarty.foreach.al.last}, {/if}
                {/foreach}
              </div>
            {/if}

            {if $arrival.occasions|count > 0}
              <div class="smart-alert-row smart-alert-row--gold">
                <span class="smart-alert-icon">🎉</span>
                {foreach from=$arrival.occasions item=occ}
                  <span class="smart-occasion-tag">{$occ.occasion_type}</span>
                {/foreach}
              </div>
            {/if}

            {if $arrival.guest_notes}
              <div class="smart-alert-row smart-alert-row--info">
                <span class="smart-alert-icon">📝</span>
                {$arrival.guest_notes|truncate:120:'...':false}
              </div>
            {/if}
          </div>

          {* Acciones *}
          <div class="smart-arrival-card__footer">
            {if $arrival.booking_status != 2}
              <button class="smart-btn smart-btn--primary smart-btn--sm"
                      onclick="openCheckinModal({$arrival.id}, '{$arrival.firstname|escape:'javascript'} {$arrival.lastname|escape:'javascript'}')">
                ✓ {l s='Hacer Check-in' mod='smart_frontdesk'}
              </button>
              <button class="smart-btn smart-btn--ghost smart-btn--sm"
                      onclick="openGuestProfile({$arrival.id_customer})">
                👤 {l s='Ver Perfil' mod='smart_frontdesk'}
              </button>
            {else}
              <button class="smart-btn smart-btn--success smart-btn--sm" disabled>
                ✓ {l s='Check-in Completado' mod='smart_frontdesk'}
              </button>
              <button class="smart-btn smart-btn--ghost smart-btn--sm"
                      onclick="printKeyCard({$arrival.id})">
                🗝 {l s='Imprimir Llave' mod='smart_frontdesk'}
              </button>
            {/if}
            <button class="smart-btn smart-btn--ghost smart-btn--sm"
                    onclick="openBookingDetail({$arrival.id})">
              ↗ {l s='Detalle' mod='smart_frontdesk'}
            </button>
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="smart-empty-state">
      <div class="smart-empty-state__icon">✈</div>
      <h3>{l s='Sin llegadas programadas para hoy' mod='smart_frontdesk'}</h3>
      <p>{l s='No hay reservas con check-in para el día de hoy.' mod='smart_frontdesk'}</p>
    </div>
  {/if}

</div>

{* ── Modal de Check-in ── *}
<div id="smart-checkin-modal" class="smart-modal-overlay" style="display:none">
  <div class="smart-modal">
    <div class="smart-modal__header">
      <h3 id="smart-checkin-modal-title">{l s='Check-in — Huésped' mod='smart_frontdesk'}</h3>
      <button onclick="closeCheckinModal()" class="smart-modal__close">✕</button>
    </div>
    <form id="smart-checkin-form" method="post"
          action="{$link->getAdminLink('AdminSmartArrivals')|escape:'html'}&action=checkIn">
      <input type="hidden" name="token" value="{$smart_module_token}">
      <input type="hidden" name="id_booking" id="checkin-booking-id">
      <div class="smart-modal__body">
        <div class="smart-form-row">
          <div class="smart-form-group">
            <label>{l s='Habitación asignada' mod='smart_frontdesk'} *</label>
            <input type="number" name="id_room" id="checkin-room-id"
                   placeholder="{l s='Nº habitación' mod='smart_frontdesk'}" required
                   class="smart-input">
          </div>
          <div class="smart-form-group">
            <label>{l s='Tipo documento' mod='smart_frontdesk'}</label>
            <select name="id_type" class="smart-select">
              <option value="RUT">RUT (Chile)</option>
              <option value="PASSPORT">Pasaporte</option>
              <option value="DNI">DNI</option>
              <option value="OTHER">Otro</option>
            </select>
          </div>
        </div>
        <div class="smart-form-row">
          <div class="smart-form-group">
            <label>{l s='Número de documento' mod='smart_frontdesk'}</label>
            <input type="text" name="id_number" placeholder="12.345.678-9"
                   class="smart-input">
          </div>
          <div class="smart-form-group">
            <label>{l s='País emisor' mod='smart_frontdesk'}</label>
            <input type="text" name="id_country" value="CL" maxlength="2"
                   class="smart-input">
          </div>
        </div>
      </div>
      <div class="smart-modal__footer">
        <button type="button" onclick="closeCheckinModal()"
                class="smart-btn smart-btn--ghost">
          {l s='Cancelar' mod='smart_frontdesk'}
        </button>
        <button type="submit" class="smart-btn smart-btn--primary">
          ✓ {l s='Confirmar Check-in' mod='smart_frontdesk'}
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openCheckinModal(bookingId, guestName) {
  document.getElementById('checkin-booking-id').value = bookingId;
  document.getElementById('smart-checkin-modal-title').textContent = 'Check-in — ' + guestName;
  document.getElementById('smart-checkin-modal').style.display = 'flex';
  document.getElementById('checkin-room-id').focus();
}
function closeCheckinModal() {
  document.getElementById('smart-checkin-modal').style.display = 'none';
}
function filterArrivals(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('.smart-arrival-card').forEach(card => {
    const name    = card.dataset.name || '';
    const booking = card.dataset.booking || '';
    card.style.display = (name.includes(q) || booking.includes(q)) ? '' : 'none';
  });
}
function filterByStatus(status, btn) {
  document.querySelectorAll('.smart-filter-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.smart-arrival-card').forEach(card => {
    if (status === 'all') { card.style.display = ''; return; }
    if (status === 'vip')     { card.style.display = card.dataset.vip === 'vip' ? '' : 'none'; return; }
    card.style.display = card.dataset.status === status ? '' : 'none';
  });
}
function openGuestProfile(customerId) {
  window.location.href = '{$link->getAdminLink('AdminSmartGuestList')|escape:'javascript'}' + '&id_customer=' + customerId;
}
function openBookingDetail(bookingId) {
  window.location.href = '{$link->getAdminLink('AdminHotelBookingDetail')|escape:'javascript'}' + '&id_order=' + bookingId;
}
function printKeyCard(bookingId) {
  window.open('{$link->getAdminLink('AdminSmartFrontDesk')|escape:'javascript'}' + '&action=printKeyCard&id_booking=' + bookingId, '_blank');
}
// Auto-refresh cada 2 minutos
setTimeout(() => window.location.reload(), 120000);
</script>
