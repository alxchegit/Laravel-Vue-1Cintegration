<template>
  <div class="amoai-td_lfb-users">
    <div class="form-group">
      <div class="form-group__title" >Настройте соответствие пользователей:</div>
      <div class="form-group__control saved_settings" v-for="(user, i) in users_init" :key="user.amo_user_id" :id="user.amo_user_id">
        <label>{{ user.amo_user_name }}</label>
        <div class="control-wrapper control--suggest ">
          <ul class="control--suggest--list js-control--suggest--list custom-scroll ">
            <li v-for="c_user in c_users"
                :key="c_user.id"
                :data-value-id="String(c_user.id)"
                :data-amo-user-id="user.amo_user_id"
                class="control--suggest--list--item">
              <span class="control--suggest--list--item-inner" :title="c_user.name">{{ c_user.name }}</span>
            </li>
          </ul>
          <input
            :data-amo-user-id="user.amo_user_id"
            :data-value-id="String(user.c_user_id)"
            data-enable-filter="y" autocomplete="off"
            name="user_match"
            class="text-input control--suggest--input js-control--suggest--input control--suggest--input-inline"
            type="text"
            placeholder="Найти"
            :value="users_init[i].c_user_name"
            data-type="">
          <b class="control--suggest--down-btn"></b>
        </div>
      </div>
    </div>
    <div class="form-group">
      <div class="form-group__control ">
        <button type="button" class="button-input button-input_blue" @click="putValues" id="amoaiSaveUsers">
            <span class="button-input-inner ">
              <span class="button-input-inner__text">Сохранить</span>
              </span>
        </button>
        <button type="button" class="button-input " @click="cancel" >
          <span class="button-input-inner ">
            <span class="button-input-inner__text">Сбросить</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
<script>
const $ = window.$
export default {
  name: 'AmoaiTdlfbUsers',
  props: {
    init_users: Array,
    c_users: Array
  },
  data () {
    return {
      users_init: this.init_users || []
    }
  },
  methods: {
    changeInput (t) {
      console.log(t)
    },
    putValues () {
      const vueThis = this
      const matchUsers = []
      $('.amoai-td_lfb-users input[name="user_match"]').each(function (indx) {
        if ($(this).attr('data-value-id') !== '') {
          matchUsers.push({
            amo_id: $(this).attr('data-amo-user-id'),
            c_user_id: $(this).attr('data-value-id')
          })
        }
      })
      console.log(matchUsers)
      if (matchUsers.length) {
        $('#amoaiSaveUsers').trigger('button:load:start')
        $.ajax({
          url: vueThis.$baseUrl + '/settings/save_users_match',
          method: 'POST',
          data: {
            account_id: vueThis.$widget.utils.getAccountId(),
            match_users: matchUsers
          },
          success: function (res) {
            if (res.success) {
              vueThis.$widget.utils.openAlert(res.payload.msg)
            } else {
              vueThis.$widget.utils.openAlert(res.error.msg, 'error')
            }
          },
          error: function (a, b, err) {
            console.log(err)
            vueThis.$widget.utils.openAlert('Error! See logs', 'error')
          },
          complete: function () {
            $('#amoaiSaveUsers').trigger('button:load:stop')
          }
        })
      }
    },
    cancel () {
      $('.amoai-td_lfb-users input[name="user_match"]').each(function (indx) {
        $(this).attr('data-value-id', '0').val('').trigger('change')
      })
    }
  }
}
</script>
<style scoped>
</style>
