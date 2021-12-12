<template>
  <div :id="id" class="amoai-td_lfb-settings">
    <div v-if="loading">
      <div class="overlay-loader">
        <span class="spinner-icon spinner-icon-abs-center"></span>
        </div>
    </div>
    <div v-else>
      <div class="amoai-td_lfb-alert_block" v-if="errors.length">
        <p v-for="(error, index) in errors" :key="index">{{error}}</p>
      </div>
      <div id="amoai-td_lfb-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['tab-button', { active: active_tabs.id === tab.id }]"
          @click="active_tabs = tab"
        >
          {{ tab.name }}
        </button>
      </div>
      <MainSettings :statuses="statuses"
                    :widget_settings="widget_settings.settings"
                    v-if="'main' === active_tabs.id"/>
      <AmoaiTdlfbUsers :init_users="init_users"
                      :c_users="c_users"
                      v-if="'users' === active_tabs.id "/>
    </div>
  </div>
</template>
<script>
import AmoaiTdlfbUsers from './components/AmoaiTdlfbUsers.vue'
import MainSettings from './components/MainSettings.vue'
import axios from 'axios'

const tabs = [
  {
    name: 'Общие',
    id: 'main'
  },
  {
    name: 'Пользователи',
    id: 'users'
  }
]

export default {
  data () {
    return {
      loading: true,
      tabs,
      active_tabs: tabs[0],
      amo_users: this.$widget.widget_methods.amoUsers(),
      c_users: [],
      errors: [],
      widget_settings: {},
      statuses: []
    }
  },
  components: {
    AmoaiTdlfbUsers,
    MainSettings
  },
  computed: {
    id: function () {
      return 'app_' + this.$widget.params.widget_code
    },
    init_users () {
      // сопоставляем амо id с 1С id пользователей
      const vueThis = this
      const a = []
      window.$.each(vueThis.amo_users, function (indx, user) {
        a.push({
          amo_user_id: user.id,
          amo_user_name: user.name,
          c_user_id: '0',
          c_user_name: ''
        })
        if ('users_match' in vueThis.widget_settings) {
          window.$.each(vueThis.widget_settings.users_match, function (zndx, setts) {
            if (+user.id === +setts.amo_user_id) {
              a[indx].c_user_id = setts.c_user_id
              if (setts.c_user_id !== '0') {
                const cUser = window._.find(vueThis.c_users, (cUs) => String(setts.c_user_id) === String(cUs.id))
                a[indx].c_user_name = cUser ? cUser.name : ''
                a[indx].c_user_id = cUser ? cUser.id : '0'
              }
              return false
            }
          })
        }
      })
      return a
    }
  },
  created () {
    const vueThis = this
    vueThis.errors = []
    vueThis.loading = true
    Promise.all([
      axios.get(`${vueThis.$widget.base_api_path}/ajax_1c/get_managers`),
      axios.get(`${vueThis.$widget.base_api_path}/settings/get`, {
        params: {
          account_id: vueThis.$widget.utils.getAccountId()
        }
      }),
      axios.get(`https://${window.AMOCRM.constant('account').subdomain}.amocrm.ru/api/v4/leads/pipelines`)
    ]).then(result => {
      const usersData = result[0].status === 200 ? result[0].data : []
      const settingsData = result[1].status === 200 ? result[1].data : []
      const pipelinesData = result[2].status === 200 ? result[2].data : []
      // менеджеры из 1С
      if (!usersData.success || usersData.payload.status !== 'success') {
        vueThis.errors.push('Fail to get managers from 1C')
        console.log('Fail to get managers from 1C', result[0])
      } else {
        vueThis.c_users = usersData.payload.response || []
      }
      // настройки из БД
      if (!settingsData.success) {
        vueThis.errors.push('Fail to get settings')
        console.log('Fail to get settings', result[1])
      } else {
        vueThis.widget_settings = settingsData.payload.response || {}
      }
      // воронки
      if (pipelinesData._embedded && pipelinesData._embedded.pipelines) {
        const pipelines = pipelinesData._embedded.pipelines
        for (const pipeline of pipelines) {
          vueThis.statuses.push({
            option: pipeline.name,
            id: pipeline.id,
            bg_color: '',
            class: 'amoai-td_lfb-disabled-select',
            disabled: true
          })
          const statuses = pipeline._embedded.statuses
          for (const status of statuses) {
            if (!status.is_editable) {
              continue
            }
            vueThis.statuses.push({
              option: status.name,
              id: pipeline.id + '_' + status.id,
              bg_color: status.color,
              class: '',
              disabled: false
            })
          }
        }
      }
      vueThis.loading = false
    }).catch(err => {
      console.log(err)
      vueThis.loading = false
      vueThis.$widget.utils.openAlert('Error', 'error')
    })
  }
}
</script>
<style scoped>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}
#amoai-td_lfb-nav
{
  border-bottom: 1px solid #7ba8cb;
  margin-bottom: 20px;
}
.amoai-td_lfb-settings button.tab-button {
  background: none;
  cursor: pointer;
  color: #bdc0c5;
  padding: 5px 20px;
  box-sizing: border-box;
}

.amoai-td_lfb-settings button.tab-button.active
{
  border-bottom: 3px solid #7ba8cb;
  color: #2e3640;
}
.amoai-td_lfb-alert_block
{
  width: 100%;
  padding: 10px;
  background-color: rgb(238, 165, 165);
  border: 1px solid rgb(236, 117, 117);
  margin: 10px;
}
.amoai-td_lfb-alert_block p
{
  text-align: center;
  list-style-type: disc;
}
</style>
