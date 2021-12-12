<template>
  <div class="amoai-td_lfb-authorization">
    <h1>Авторизация</h1>
    <label for="username">Логин</label>
    <input type="text" v-model="userName" id="username">
    <label for="password">Пароль</label>
    <input type="text" v-model="password" id="password">
  </div>
</template>
<script>
export default {
  data () {
    return {
      userName: '',
      password: ''
    }
  }
}
</script>
