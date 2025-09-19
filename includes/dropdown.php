<header class="bg-white shadow-md sticky top-0 z-50" x-data="{ openMobile: false, openDropdown: false }">
    <div class="max-w-7xl mx-auto flex justify-between items-center p-4 md:p-6">
      <a href="/" class="flex items-center space-x-3">
        <img src="img/logo-automoclick.png" alt="Automoclick" class="h-12 w-auto" />
      </a>
      <nav class="hidden md:flex space-x-8 items-center text-gray-700 font-medium">
        <a href="/" class="hover:text-green-600 transition">Accueil</a>
        <a href="pro" class="hover:text-green-600 transition">Prestataires</a>
        <!-- <a href="boutique" class="hover:text-green-600 transition">Boutique</a> -->
        <a href="contact" class="hover:text-green-600 transition">Contact</a>

        <?php if (!isset($_SESSION['id_client']) && !isset($_SESSION['id_pro'])) { ?>
          <a href="connexion" class="hover:text-green-600">Connexion</a>
          <a href="inscription-pro"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-semibold transition">Inscription
            pro</a>
          <a href="inscription-particulier"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-semibold transition">Inscription
            particulier</a>
        <?php } else { ?>
          <div class="relative" x-data="{ openDropdown: false }" @click.outside="openDropdown = false">
            <button @click="openDropdown = !openDropdown"
              class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
              <span class="material-symbols-outlined mr-2">
                <?= isset($_SESSION['id_pro']) ? 'enterprise' : 'person' ?>
              </span>
              <?= isset($_SESSION['id_pro']) ? htmlspecialchars($_SESSION['name_company']) : htmlspecialchars($_SESSION['prenom']) ?>
              <svg class="w-4 h-4 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div x-show="openDropdown" x-transition
              class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-lg z-50">
              <a href="dashbord_c" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100"><span
                  class="material-symbols-outlined mr-2">dashboard</span> Tableau de bord</a>
              <a href="mes_rdvs.php" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100"><span
                  class="material-symbols-outlined mr-2">event</span> Rendez-vous</a>
              <a href="message" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 relative"><span
                  class="material-symbols-outlined mr-2">chat</span> Messages</a>
              <a href="z" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-100"><span
                  class="material-symbols-outlined mr-2">logout</span> Déconnexion</a>
            </div>
          </div>
        <?php } ?>
      </nav>
      <button @click="openMobile = !openMobile" class="md:hidden focus:outline-none">
        <svg class="w-7 h-7 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>
    <div x-show="openMobile" x-transition class="md:hidden px-4 py-4 space-y-2 bg-white shadow-inner">
      <a href="/" class="block px-4 py-2 rounded hover:bg-green-50">Accueil</a>
      <a href="pro" class="block px-4 py-2 rounded hover:bg-green-50">Prestataires</a>
      <!-- <a href="#boutique" class="block px-4 py-2 rounded hover:bg-green-50">Boutique</a> -->
      <a href="contact" class="block px-4 py-2 rounded hover:bg-green-50">Contact</a>

      <?php if (!isset($_SESSION['id_client']) && !isset($_SESSION['id_pro'])) { ?>
        <a href="connexion" class="block text-green-600 font-semibold text-center">Connexion</a>
        <a href="inscription-pro"
          class="block bg-green-600 text-white py-2 rounded-md text-center font-semibold hover:bg-green-700">Inscription
          pro</a>
        <a href="inscription-particulier"
          class="block bg-green-600 text-white py-2 rounded-md text-center font-semibold hover:bg-green-700">Inscription
          particulier</a>
      <?php } else { ?>
        <div x-data="{ openDropdownMobile: false }" class="border-t pt-4">
          <button @click="openDropdownMobile = !openDropdownMobile"
            class="w-full flex justify-between items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
            <div class="flex items-center">
              <span class="material-symbols-outlined mr-2">
                <?= isset($_SESSION['id_pro']) ? 'enterprise' : 'person' ?>
              </span>
              <?= isset($_SESSION['id_pro']) ? htmlspecialchars($_SESSION['name_company']) : htmlspecialchars($_SESSION['prenom']) ?>
            </div>
            <svg class="w-4 h-4 ml-2 transform" :class="{ 'rotate-180': openDropdownMobile }"
              xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div x-show="openDropdownMobile" x-transition class="mt-2 space-y-2">
            <a href="dashbord_c" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                class="material-symbols-outlined mr-2">dashboard</span> Tableau de bord</a>
            <a href="mes_rdvs.php" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                class="material-symbols-outlined mr-2">event</span> Rendez-vous</a>
            <a href="message" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                class="material-symbols-outlined mr-2">chat</span> Messages</a>
            <a href="setting" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                class="material-symbols-outlined mr-2">settings</span> Paramètres</a>
            <a href="z" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100"><span
                class="material-symbols-outlined mr-2">logout</span> Déconnexion</a>
          </div>
        </div>
      <?php } ?>
    </div>

  </header>
  