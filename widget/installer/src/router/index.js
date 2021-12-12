import Vue from 'vue'
import VueRouter from 'vue-router'
import Users from '../views/Users.vue'
import Authorization from '../views/Authorization.vue'
import Statuses from '../components/Statuses.vue'

Vue.use(VueRouter)

const routes = [
  {
    path: '/authorization',
    name: 'Авторизация',
    component: Authorization
  },
  {
    path: '/users',
    name: 'Сопоставление пользователей',
    component: Users
  },
  {
    path: '/statuses',
    name: 'Статус в воронке',
    component: Statuses
  }
]

const router = new VueRouter({
  mode: 'hash',
  base: process.env.BASE_URL,
  routes
})

export default router
