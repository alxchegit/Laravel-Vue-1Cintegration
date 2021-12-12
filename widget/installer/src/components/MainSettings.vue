<template>
  <div class="amoai-td_lfb-main_settings">
    <!-- Синхронизация -->
    <div class="form-group">
      <div class="form-group__title" >Синхронизация</div>
      <p>Первичная синхонизация контрагентов 1С и компаний в АмоСРМ.
          Будет произведено скачивание и получение информации по контрагентам в 1С и создание компаний, на основе этих данных.
          В случае совпадения по ключу, приоритет у данных из 1С</p>
      <label class="control-checkbox">
        <div class="control-checkbox__body">
          <input type="checkbox"
            v-model="checked"
            name="checkbox"
            data-value="">
          <span class="control-checkbox__helper "></span>
        </div>
        <div class="control-checkbox__text element__text"
         title="Провести синхронизацию?">Провести синхронизацию?</div>
      </label>
      <button type="button"
          @click="syncAction"
          :class="{'button-input_blue': checked}"
          id="amoaiSyncCompany"
          class="button-input"
          ><span class="button-input-inner "><span class="button-input-inner__text">Синхронизация</span></span></button>
    </div>
    <!-- Дополнительные настройки -->
   <Statuses :statuses="statuses"
             :widget_settings="widget_settings"/>
  </div>
</template>

<script>
import axios from 'axios'
import Statuses from './Statuses.vue'
export default {
  name: 'MainSettings',
  props: {
    statuses: Array,
    widget_settings: Array
  },
  components: {
    Statuses
  },
  data () {
    return {
      checked: false,
      processed: false
    }
  },
  methods: {
    syncAction () {
      const vueThis = this
      if (vueThis.checked && !vueThis.processed) {
        vueThis.processed = true
        window.$('#amoaiSyncCompany').trigger('button:load:start')
        axios({
          method: 'post',
          url: vueThis.$baseUrl + '/settings/sync_customers',
          headers: {
            'Content-type': 'application/json; charset=UTF-8'
          },
          params: {
            account_id: vueThis.$widget.utils.getAccountId(),
            managers_id: vueThis.$widget._AMOCRM_.constant('user').id,
            is_admin: vueThis.$widget._AMOCRM_.constant('user_rights').is_admin
          }
        }).then(res => {
          console.log(res)
          if (res.data.success) {
            vueThis.$widget.utils.openAlert(res.data.payload.response)
          } else {
            vueThis.$widget.utils.openAlert('Ошибка!', 'error')
          }
        }).catch(err => {
          console.log(err)
          vueThis.$widget.utils.openAlert('Critical error!', 'error')
        }).then(x => {
          window.$('#amoaiSyncCompany').trigger('button:load:stop')
          vueThis.processed = false
        })
      }
    }
  }
}
</script>
<style scoped>
.amoai-td_lfb-main_settings p
{
  margin: 20px 0;
  font-size: 14px;
  color: dimgrey;
}
.amoai-td_lfb-main_settings button.button-input
{
  display: block;
  margin: 20px 0;
}
</style>
