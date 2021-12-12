import Vue from 'vue'
import App from './App.vue'
import AppCCard from './AppCcard.vue'
import router from './router'
import store from './store'
Vue.config.productionTip = false
export default {
  render (widget, selector, baseUrl) {
    Vue.prototype.$widget = widget
    Vue.prototype.$baseUrl = baseUrl
    window[widget.params.widget_code] = {}
    window[widget.params.widget_code].vue = new Vue({
      store,
      router,
      render: h => h(App)
    }).$mount(selector)
  }
}
export class Ccard {
  render (widget, selector, baseUrl) {
    Vue.prototype.$widget = widget
    Vue.prototype.$baseUrl = baseUrl
    window[widget.params.widget_code] = {}
    window[widget.params.widget_code].vue = new Vue({
      store,
      router,
      render: h => h(AppCCard)
    }).$mount(selector)
  }
}
