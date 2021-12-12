<template>
  <div data-id="integration_order_tab" class="linked-forms__group-wrapper linked-forms__group-wrapper_main js-cf-group-wrapper" style="display:none">
    <h2>{{ msg }}</h2>
    <input type="text" v-model="msg">
  </div>
</template>
<script>
export default {
  data () {
    return {
      msg: 'Hello I am new Vue'
    }
  }
}
</script>
<style scoped></style>
