<template>
  <div class="amoai-td_lfb-users">
    <div class="form-group">
      <div class="form-group__title" >Настройте соответствие пользователей:</div>
      <div class="form-group__control saved_settings" v-for="(user, indx) in users" :key="user.id" :id="user.id">
        <label>{{ user.name }}</label>
        <div class="control-wrapper control--suggest ">
          <select name="c_users" id="amoai_c_users" v-model="values[indx]">
            <option v-for="c_user in c_users" :key="c_user.id" :value="String(c_user.id)">{{ c_user.name }}</option>
          </select>
        </div>
      </div>
    </div>
    <div class="form-group">
      <div class="form-group__control ">
          <button type="button" class="button-input button-input_blue" @click="$emit('save', values)">
            <span class="button-input-inner ">
              <span class="button-input-inner__text">Сохранить</span>
              </span>
          </button>
          <button type="button" class="button-input " @click="cancel" ><span class="button-input-inner "><span class="button-input-inner__text">Отменить</span></span></button>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  name: 'Users',
  data () {
    return {
      users: this.$widget.widget_methods.allUsers(),
      c_users: this.$widget.widget_methods.cUsers(),
      values: []
    }
  },
  methods: {
    putValues (e) {
      this.values.push({
        amo_user_id: this.c_users[e.target.attributes['data-value-id'].value - 1].id_1c,
        c_user_id: e.target.attributes['data-1c-user-id'].value
      })
      console.log(this.values)
    },
    save () {
      console.log(this.values)
    }
  }
}
</script>
