import { createRouter, createWebHistory } from 'vue-router'
import { state } from '../store/appStore'
import LandingView from '../views/LandingView.vue'
import LoginView from '../views/LoginView.vue'
import DashboardView from '../views/DashboardView.vue'
import ProfileView from '../views/ProfileView.vue'
import GroupsView from '../views/GroupsView.vue'
import MatchmakingView from '../views/MatchmakingView.vue'
import AdminView from '../views/AdminView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: LandingView, meta: { guestOnly: true, title: 'StudyMate' } },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true, title: 'Login' } },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'Dashboard' } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true, title: 'Profil' } },
    { path: '/groups', name: 'groups', component: GroupsView, meta: { requiresAuth: true, title: 'Grup Belajar' } },
    { path: '/matchmaking', name: 'matchmaking', component: MatchmakingView, meta: { requiresAuth: true, title: 'Smart Match' } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { requiresAuth: true, adminOnly: true, title: 'Admin' } }
  ]
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !state.user?.id) {
    return '/login'
  }

  if (to.meta.guestOnly && state.user?.id) {
    return '/dashboard'
  }

  if (to.meta.adminOnly && state.user?.role !== 'admin') {
    return '/dashboard'
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | StudyMate` : 'StudyMate'
})

export default router
