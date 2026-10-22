<template>
  <footer class="bg-inverse-surface text-inverse-on-surface mt-20">
    <div class="max-w-7xl mx-auto px-8 py-16">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
        <!-- Brand -->
        <div class="md:col-span-2">
          <img src="/logo.png" alt="UtterLazy" class="h-12 mb-4 object-contain" />
          <p class="text-inverse-on-surface/60 max-w-sm leading-relaxed">
            Curating Bhiwani's finest culinary artisans. From heritage restaurants to hidden gems — discover, order, and savor the best food in your city.
          </p>
          <div class="flex gap-4 mt-6">
            <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-primary transition-colors">
              <span class="material-symbols-outlined text-[18px]">share</span>
            </a>
            <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-primary transition-colors">
              <span class="material-symbols-outlined text-[18px]">mail</span>
            </a>
            <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-primary transition-colors">
              <span class="material-symbols-outlined text-[18px]">call</span>
            </a>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h3 class="font-bold text-sm uppercase tracking-widest mb-4 text-inverse-on-surface/80">Explore</h3>
          <ul class="space-y-3">
            <li><router-link to="/" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Home</router-link></li>
            <li><router-link to="/search" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Search</router-link></li>
            <li><router-link to="/orders" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">My Orders</router-link></li>
            <li><router-link to="/favorites" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Favorites</router-link></li>
          </ul>
        </div>

        <!-- Legal -->
        <div>
          <h3 class="font-bold text-sm uppercase tracking-widest mb-4 text-inverse-on-surface/80">Company</h3>
          <ul class="space-y-3">
            <li><a href="#" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">About UtterLazy</a></li>
            <li><a href="#" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Partner with us</a></li>
            <li><a href="#" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Terms of Service</a></li>
            <li><a href="#" class="text-inverse-on-surface/60 hover:text-inverse-primary transition-colors text-sm">Privacy Policy</a></li>
          </ul>
        </div>
      </div>

      <!-- Bottom bar -->
      <div class="mt-12 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-inverse-on-surface/40 text-sm">© 2026 UtterLazy. All rights reserved.</p>
        <p class="text-inverse-on-surface/40 text-sm">Made with ❤️ in Bhiwani</p>
      </div>
    </div>
  </footer>
</template>
