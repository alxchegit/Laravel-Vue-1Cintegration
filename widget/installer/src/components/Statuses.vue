<template>
  <div class="amoai-td_lfb-additional_main_settings ">
    <div  class="form-group js-amoai-td_lfb-saved_settings">
      <div class="form-group__title" >Воронки</div>
      <p>Укажите на какой этап воронки необходимо переместить сделку при создании заказа, и виджет автоматически сделает это.</p>
      <div class="amoai-td_lfb-statuses_check" v-html="statuses_check"></div>
      <div class="amoai-td_lfb-select_statuses" v-html="select_statuses"></div>
    </div>
    <div  class="form-group">
      <div class="form-group__title" >Почта</div>
      <p>Укажите на какой адрес электронной почты отправлять извещение о создании в АмоСРМ новой компании</p>
      <div>
        <div class="amoai-td_lfb-legal_mail" v-html="legal_email"></div>

      </div>
    </div>
  </div>
</template>

<script>
const $ = window.$
export default {
  name: 'Statuses',
  props: {
    statuses: Array,
    widget_settings: Array
  },
  data () {
    return {
    }
  },
  mounted () {
    const vueThis = this
    $('body').off('controls:change', '.js-amoai-td_lfb-saved_settings input')
      .on('controls:change', '.js-amoai-td_lfb-saved_settings input', function (e) {
        const data = {
          account_id: vueThis.$widget.utils.getAccountId()
        }
        data[$(this).attr('name')] = $(this).val()
        if ($(this).attr('name') === 'status_check') {
          data[$(this).attr('name')] = $(this).prop('checked')
        }
        vueThis.saveSettingMethod(data)
      })
    $('body').off('click', '#legalEmailSave')
      .on('click', '#legalEmailSave', function (e) {
        const $button = $(this)
        $button.trigger('button:load:start')
        const value = $('.amoai-td_lfb-legal_mail input').val().trim()
        if (value.length > 3 && vueThis.validateMail(value)) {
          const data = {
            account_id: vueThis.$widget.utils.getAccountId(),
            legal_email: value
          }
          vueThis.saveSettingMethod(data).finally(() => {
            $button.trigger('button:load:stop')
          })
        } else {
          vueThis.$widget.utils.openAlert('Некорректный email', 'error')
          $button.trigger('button:load:stop')
        }
      })
  },
  methods: {
    validateMail (email) {
      return /.+@.+\..+/.test(email)
    },
    saveSettingMethod (data) {
      const vueThis = this
      return new Promise(function (resolve, reject) {
        $.ajax({
          url: `${vueThis.$widget.base_api_path}/settings/save`,
          method: 'POST',
          data: data,
          success: function (res) {
            if (res.success) {
              vueThis.$widget.utils.openAlert('Сохранено!')
              resolve(res)
            } else {
              vueThis.$widget.utils.openAlert('Не удалось сохранить.</br>' + res.error, 'error')
              reject(res)
            }
            console.log('settings', res)
          },
          error: function (a, b, err) {
            vueThis.$widget.utils.openAlert('Критическая ошибка!</br>' + err.message, 'error')
            reject(err)
            console.log('error from settings', err)
          }
        })
      })
    }
  },
  computed: {
    select_statuses () {
      const vueThis = this
      const selected = vueThis.widget_settings.length ? vueThis.widget_settings[0].statuses : '0'
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
      const checked = vueThis.widget_settings.length ? vueThis.widget_settings[0].status_check : false
      return this.$widget.render(
        { ref: '/tmpl/controls/checkbox.twig' }, {
          name: 'status_check',
          button_class_name: 'amoai-td_lfb-status_chek',
          checked: checked,
          text: 'Активировать'
        })
    },
    legal_email () {
      const vueThis = this
      const value = vueThis.widget_settings.length ? vueThis.widget_settings[0].legal_email : ''
      return this.$widget.render(
        { ref: '/tmpl/controls/input.twig' }, {
          name: 'legal_email',
          type: 'text',
          value: value
        }) + this.$widget.render(
        { ref: '/tmpl/controls/button.twig' },
        {
          class_name: 'amoai-td_lfb-save_legal_email',
          text: 'Сохранить',
          id: 'legalEmailSave'
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
.amoai-td_lfb-legal_mail {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-content: center;
  justify-content: flex-start;
  align-items: stretch;
}

.amoai-td_lfb-legal_mail input.text-input {
  width: 225px;
}
</style>
