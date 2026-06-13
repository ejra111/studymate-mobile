<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const features = [
  {
    title: 'Smart Matchmaking',
    description: 'Algoritma AI kami mencocokkan Anda dengan partner belajar berdasarkan mata kuliah, minat, dan ketersediaan waktu.',
    icon: '✨'
  },
  {
    title: 'Golden Hour Scheduling',
    description: 'Temukan waktu terbaik untuk berkumpul dengan asisten penjadwalan cerdas yang menganalisis jadwal semua anggota.',
    icon: '🕒'
  },
  {
    title: 'Study Groups',
    description: 'Buat atau bergabung dengan grup belajar yang terorganisir dengan kapasitas, lokasi, dan topik yang jelas.',
    icon: '👥'
  }
]

const benefits = [
  {
    title: 'Belajar Lebih Efektif',
    description: 'Diskusi aktif dengan partner yang tepat membantu pemahaman materi lebih cepat dibandingkan belajar sendiri.',
    icon: '📈'
  },
  {
    title: 'Komunitas Akademik',
    description: 'Bangun jaringan pertemanan antar program studi dengan minat akademik yang serupa.',
    icon: '🤝'
  },
  {
    title: 'Terorganisir & Terarah',
    description: 'Semua aktivitas belajar tercatat dan terjadwal dengan rapi dalam satu dashboard modern.',
    icon: '📋'
  }
]

const testimonials = [
  {
    name: 'Raffa Anung',
    role: 'Mahasiswa Informatika',
    text: 'StudyMate sangat membantu saya menemukan teman untuk mengerjakan tugas besar Web. Fitur Golden Hour-nya benar-benar penyelamat!',
    avatar: '#2563eb'
  },
  {
    name: 'Akbar Jundi',
    role: 'Mahasiswa Sistem Informasi',
    text: 'UI-nya sangat modern dan intuitif. Rekomendasi partner-nya akurat, saya jadi punya teman diskusi yang sefrekuensi.',
    avatar: '#7c3aed'
  },
  {
    name: 'Alivio',
    role: 'Mahasiswa DKV',
    text: 'Sangat memudahkan mencari grup belajar UI/UX. Sekarang belajar jadi tidak membosankan lagi.',
    avatar: '#db2777'
  }
]

function scrollTo(id) {
  const el = document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: 'smooth' })
}

function goLogin() {
  router.push('/login')
}
</script>

<template>
  <div class="landing-page">
    <!-- Navbar -->
    <nav class="landing-nav glass-card">
      <div class="nav-container">
        <div class="nav-left">
          <div class="logo-box">
            <span class="logo-icon brand-mark">SM</span>
            <span class="logo-text">StudyMate</span>
          </div>
        </div>
        
        <div class="nav-center">
          <button @click="scrollTo('features')">Features</button>
          <button @click="scrollTo('benefits')">Benefits</button>
          <button @click="scrollTo('testimonials')">Testimonials</button>
        </div>
        
        <div class="nav-right">
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <header class="landing-hero">
      <div class="hero-content">
        <p class="eyebrow">The Future of Campus Collaboration</p>
        <h1>StudyMate</h1>
        <p class="hero-desc">
          StudyMate adalah platform kolaborasi akademik modern yang dirancang khusus untuk mahasiswa. 
          Kami membantu Anda menemukan partner belajar yang tepat, mengelola grup studi secara cerdas, 
          dan mengoptimalkan waktu belajar melalui teknologi AI yang adaptif.
        </p>
        <div class="hero-btns">
          <button class="primary-btn large-btn" @click="goLogin">Mulai Sekarang</button>
          <button class="ghost-btn large-btn" @click="scrollTo('features')">Pelajari Fitur</button>
        </div>
      </div>
      <div class="hero-visual">
        <div class="visual-card glass-card">
          <div class="card-line"></div>
          <div class="card-line short"></div>
          <div class="card-grid">
            <div class="grid-item"></div>
            <div class="grid-item"></div>
            <div class="grid-item"></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Features Section -->
    <section id="features" class="landing-section">
      <div class="section-head">
        <p class="eyebrow">Fitur Unggulan</p>
        <h2>Didesain untuk Efisiensi Belajar</h2>
      </div>
      <div class="features-grid">
        <article v-for="f in features" :key="f.title" class="glass-card feature-item">
          <div class="feature-icon">{{ f.icon }}</div>
          <h3>{{ f.title }}</h3>
          <p>{{ f.description }}</p>
        </article>
      </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="landing-section dark-bg">
      <div class="section-head">
        <p class="eyebrow">Mengapa StudyMate?</p>
        <h2>Lebih dari Sekadar Grup Belajar</h2>
      </div>
      <div class="benefits-grid">
        <article v-for="b in benefits" :key="b.title" class="benefit-item">
          <div class="benefit-icon">{{ b.icon }}</div>
          <div class="benefit-text">
            <h3>{{ b.title }}</h3>
            <p>{{ b.description }}</p>
          </div>
        </article>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="landing-section">
      <div class="section-head">
        <p class="eyebrow">Apa Kata Mereka?</p>
        <h2>Dipercaya oleh Mahasiswa Berbagai Jurusan</h2>
      </div>
      <div class="testimonials-grid">
        <article v-for="t in testimonials" :key="t.name" class="glass-card testimonial-item">
          <p class="testimonial-text">"{{ t.text }}"</p>
          <div class="testimonial-user">
            <div class="avatar small-avatar" :style="{ background: t.avatar }">{{ t.name.charAt(0) }}</div>
            <div>
              <strong>{{ t.name }}</strong>
              <p class="small-muted">{{ t.role }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
      <div class="footer-container">
        <div class="footer-left">
          <div class="logo-box">
            <span class="logo-icon">🎓</span>
            <span class="logo-text">StudyMate</span>
          </div>
          <p class="small-muted">© 2026 StudyMate Platform. All rights reserved.</p>
        </div>
        <div class="footer-right">
          <p>Dibuat untuk kolaborasi akademik yang lebih baik.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.landing-page {
  background-color: #0f172a;
  color: #f8fafc;
  min-height: 100vh;
}

/* Navbar */
.landing-nav {
  position: fixed;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%);
  width: 90%;
  max-width: 1200px;
  z-index: 1000;
  padding: 0.75rem 2rem;
  border-radius: 999px;
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo-box {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-icon {
  width: 40px;
  height: 40px;
  font-size: 0.95rem;
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.5px;
  padding-bottom: 0.1em;
  background: linear-gradient(135deg, #f8fafc 0%, #a78bfa 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.nav-center {
  display: flex;
  gap: 2rem;
}

.nav-center button {
  background: none;
  border: none;
  color: #94a3b8;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.2s;
}

.nav-center button:hover {
  color: #f8fafc;
}

.nav-right {
  display: flex;
  gap: 1rem;
}

.small-btn {
  padding: 0.5rem 1.25rem;
  font-size: 0.9rem;
}

/* Hero */
.landing-hero {
  padding: 12rem 2rem 6rem;
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 4rem;
  align-items: center;
}

.hero-content h1 {
  font-size: 5rem;
  font-weight: 900;
  margin-bottom: 1.5rem;
  line-height: 1.2;
  padding-bottom: 0.2em;
  background: linear-gradient(135deg, #f8fafc 30%, #7c3aed 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  font-size: 1.25rem;
  color: #94a3b8;
  line-height: 1.6;
  margin-bottom: 2.5rem;
  max-width: 600px;
}

.hero-btns {
  display: flex;
  gap: 1.5rem;
}

.large-btn {
  padding: 1rem 2rem;
  font-size: 1.1rem;
}

.hero-visual {
  display: flex;
  justify-content: center;
}

.visual-card {
  width: 300px;
  height: 400px;
  padding: 2rem;
  transform: rotate(5deg);
  border: 1px solid rgba(124, 58, 237, 0.3);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.card-line {
  height: 8px;
  background: rgba(148, 163, 184, 0.2);
  border-radius: 4px;
}

.card-line.short {
  width: 60%;
}

.card-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1rem;
}

.grid-item {
  height: 80px;
  background: rgba(124, 58, 237, 0.1);
  border-radius: 12px;
  border: 1px solid rgba(124, 58, 237, 0.2);
}

/* Sections */
.landing-section {
  padding: 8rem 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.dark-bg {
  max-width: 100%;
  background: rgba(15, 23, 42, 0.5);
}

.section-head {
  text-align: center;
  margin-bottom: 4rem;
}

.section-head h2 {
  font-size: 3rem;
  font-weight: 800;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.feature-item {
  padding: 2.5rem;
  text-align: center;
  transition: transform 0.3s;
}

.feature-item:hover {
  transform: translateY(-10px);
}

.feature-icon {
  font-size: 3rem;
  margin-bottom: 1.5rem;
}

.feature-item h3 {
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.benefits-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3rem;
}

.benefit-item {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

.benefit-icon {
  font-size: 2.5rem;
  background: rgba(124, 58, 237, 0.1);
  padding: 1rem;
  border-radius: 20px;
}

.benefit-text h3 {
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.testimonial-item {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 2rem;
}

.testimonial-text {
  font-style: italic;
  line-height: 1.6;
  color: #cbd5e1;
}

.testimonial-user {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.small-avatar {
  width: 40px;
  height: 40px;
  font-size: 1rem;
}

/* Footer */
.landing-footer {
  padding: 4rem 2rem;
  border-top: 1px solid rgba(148, 163, 184, 0.1);
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-left {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

@media (max-width: 1024px) {
  .hero-content h1 { font-size: 4rem; }
  .landing-hero { grid-template-columns: 1fr; text-align: center; }
  .hero-content { display: flex; flex-direction: column; align-items: center; }
  .hero-visual { display: none; }
  .features-grid, .benefits-grid, .testimonials-grid { grid-template-columns: 1fr; }
  .nav-center { display: none; }
}
</style>
