<header class="md:hidden flex items-center justify-between bg-white shadow p-4">
    <h2 class="text-xl font-bold text-green-600">Automoclick - PRO</h2>
    <button id="menuBtn" class="text-3xl text-green-600 font-bold">&#9776;</button>
</header>

<aside id="mobileMenu"
    class="w-full md:w-64 bg-white shadow-lg p-4 space-y-4 md:space-y-0 md:flex md:flex-col md:fixed md:top-0 md:left-0 md:h-full hidden md:block z-50">
    <div class="p-4 border-b hidden md:block">
        <h2 class="text-xl font-bold text-green-600">Automoclick - PRO</h2>
    </div>
    <nav class="flex flex-col md:p-4 space-y-2 flex-grow">
        
            <a href="dashbord" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Tableau
                de
                bord</a>
               <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
            <a href="profil"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Profil</a>
            <a href="prestation" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Mes
                prestations</a>
            <a href="d&f" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Devis et
                factures</a>
            <a href="setting"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Paramètres</a>
            <?php endif; ?>
            <a href="z" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Se
                déconnecter</a>
    </nav>
    <div class="text-center text-sm text-gray-500 border-t pt-4">&copy; 2025 Automoclick</div>
</aside>