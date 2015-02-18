{* vim: set ts=2 sw=2 sts=2 et: *}



<table cellspacing="1" cellpadding="5" class="settings-table">

  <tr>
    <td class="setting-name">
      <label for="settings_accountid">{t(#Account ID#)}</label>
    </td>
    <td>
      <input type="text" id="settings_accountid" name="settings[accountid]" value="{paymentMethod.getSetting(#accountid#)}" class="validate[required,maxSize[255]]" />
    </td>
  </tr>

  <tr>
    <td class="setting-name">
      <label for="settings_siteid">{t(#Site ID#)}</label>
    </td>
    <td>
      <input type="text" id="settings_siteid" name="settings[siteid]" value="{paymentMethod.getSetting(#siteid#)}" class="validate[required,maxSize[255]]" />
    </td>
  </tr>

  <tr>
    <td class="setting-name">
      <label for="settings_pub_cert">{t(#Site Secure Code#)}</label>
    </td>
    <td>
      <input type="text" id="settings_sitesecurecode" name="settings[sitesecurecode]" value="{paymentMethod.getSetting(#siteid#)}" class="validate[required,maxSize[255]]" />
    </td>
  </tr>

  <tr>
    <td class="setting-name">
      <label for="settings_daysactive">{t(#Days Active#)}</label>
    </td>
   <td>
      <input type="text" id="settings_daysactive" name="settings[daysactive]" value="{paymentMethod.getSetting(#daysactive#)}" class="validate[required,maxSize[255]]" />
    </td>
  </tr>

 
  <!--<tr>
    <td class="setting-name">
      <label for="settings_currency">{t(#Currency#)}</label>
    </td>
    <td>
    <select id="settings_currency" name="settings[currency]">
      <option value="EUR" selected="{isSelected(paymentMethod.getSetting(#currency#),#EUR#)}">EUR</option>
    </select>
    </td>-->
  </tr>

  <tr>
    <td class="setting-name">
      <label for="settings_prefix">{t(#Invoice number prefix#)}</label>
    </td>
    <td>
      <input type="text" id="settings_prefix" value="{paymentMethod.getSetting(#prefix#)}" name="settings[prefix]" />
    </td>
  </tr>

  <tr>
    <td class="setting-name">
    <label for="settings_environment">{t(#Test/Live mode#)}</label>
    </td>
    <td>
    <select id="settings_environment" name="settings[environment]">
      <option value="Y" selected="{isSelected(paymentMethod.getSetting(#environment#),#Y#)}">{t(#Test mode: Test#)}</option>
      <option value="N" selected="{isSelected(paymentMethod.getSetting(#environment#),#N#)}">{t(#Test mode: Live#)}</option>
    </select>
    </td>
  </tr>

</table>

<script type="text/javascript">
  jQuery("#settings_currency").val("{paymentMethod.getSetting(#currency#)}");
</script>
