{*
 * SmartHotelOS — Dashboard Ejecutivo
 * Grupo Smart de Administración
 *}
<link rel="stylesheet"
      href="{$smarty.const._MODULE_DIR_}smart_dashboard/../../../smart-themes/smart-luxury/css/admin.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@300&display=swap" rel="stylesheet">

<div id="smart-executive-dashboard"></div>

<script type="module">
  window.SMART_API_KEY     = '{$smart_api_key|escape:'javascript'}';
  window.SMART_PROPERTY_ID = {$smart_property_id|intval};

  /* Cargar Vue 3 + componente */
  import { initExecutiveDashboard } from
    '{$smarty.const._MODULE_DIR_}smart_dashboard/views/js/ExecutiveDashboard.js';
  initExecutiveDashboard('#smart-executive-dashboard');
</script>
