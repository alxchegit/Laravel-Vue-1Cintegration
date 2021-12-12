<template>
  <div  class="form-group amoai-td_lfb-statuses_settings">
    <div class="form-group__title" >Почта</div>
    <p>Укажите на какой адрес электронной почты отправлять извещение о создании в АмоСРМ новой компании</p>
    <div class="amoai-td_lfb-legal_mail" v-html="legal_mail"></div>
  </div>
</template>

<script>
const $ = window.$
export default {
  name: 'Statuses',
  props: {
    statuses: Array,
    statuses_settings: Array
  },
  data () {
    return {
    }
  },
  mounted () {
    const vueThis = this
    $('body').off('controls:change', '.amoai-td_lfb-widget_settings input')
      .on('controls:change', '.amoai-td_lfb-widget_settings input', function (e) {
        const data = {
          account_id: vueThis.$widget.utils.getAccountId()
        }
        data[$(this).attr('name')] = $(this).val()
        if ($(this).attr('name') === 'status_check') {
          data[$(this).attr('name')] = $(this).prop('checked')
        }
        $.ajax({
          url: `${vueThis.$widget.base_api_path}/settings/save`,
          method: 'POST',
          data: data,
          success: function (res) {
            if (res.success) {
              vueThis.$widget.utils.openAlert('Сохранено!')
            } else {
              vueThis.$widget.utils.openAlert('Не удалось сохранить.</br>' + res.error, 'error')
            }
            console.log('settings', res)
          },
          error: function (a, b, err) {
            vueThis.$widget.utils.openAlert('Критическая ошибка!</br>' + err.message, 'error')
            console.log('error from settings', err)
          }
        })
      })
  },
  computed: {
    select_statuses () {
      const vueThis = this
      const selected = vueThis.statuses_settings[0].statuses || '0'
      return this.$widget.render(
        { ref: '/tmpl/controls/select.twig' }, {
          name: 'statuses',
          button_class_name: 'amoai-td_lfb-select_statuses',
          items: vueThis.statuses,
          selected: selected
        })
    },
    statuses_check () {
      const vueThis = this
      const checked = vueThis.statuses_settings[0].status_check || false
      return this.$widget.render(
        { ref: '/tmpl/controls/checkbox.twig' }, {
          name: 'status_chek',
          button_class_name: 'amoai-td_lfb-status_chek',
          checked: checked,
          text: 'Автоматический перевод'
        })
    }
  }
}
</script>
<style scoped>
p {
  margin: 20px 0;
  font-size: 14px;
  color: #696969;
}
.amoai-td_lfb-statuses_check,
.amoai-td_lfb-select_statuses
{
  width: 50%;
  margin: 20px 0;
}

</style>
