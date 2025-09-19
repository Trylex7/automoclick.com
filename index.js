
document.addEventListener("DOMContentLoaded", function () {
    const billingSwitch = document.getElementById("billing-switch");
    const prices = document.querySelectorAll(".price");
    const discounts = document.querySelectorAll(".discount");

    // Remises associées à chaque plan (dans l'ordre des éléments)
    const discountsByPlan = ['10%', '15%', '20%'];

    // 📌 Mise à jour des prix et des remises en fonction du toggle
    function updatePrices() {
        const annually = billingSwitch.checked;

        prices.forEach((priceElement, index) => {
            const monthly = parseFloat(priceElement.dataset.monthly);
            const annual = parseFloat(priceElement.dataset.yearly);

            if (annually) {
                priceElement.innerHTML = annual.toFixed(2) + '<sup class="text-lg">€</sup>/an';
                if (discounts[index]) {
                    discounts[index].textContent = `Économisez ${discountsByPlan[index]}`;
                    discounts[index].style.display = "block";
                }
            } else {
                priceElement.innerHTML = monthly.toFixed(2) + '<sup class="text-lg">€</sup>/mois';
                if (discounts[index]) discounts[index].style.display = "none";
            }
        });
    }

    billingSwitch.addEventListener("change", updatePrices);
    updatePrices(); // Initialisation au chargement

    // 🚀 Ajout du token + redirection dynamique au clic
    document.querySelectorAll('.sub-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            const isAnnual = billingSwitch.checked;
            const plan = this.dataset.plan;

            let nom, prix;

            if (plan === 'top') {
                nom = 'top';
                prix = isAnnual ? 359.10 : 39.99;
            } else if (plan === 'restyle') {
                nom = 'restyle';
                prix = isAnnual ? 519.20 : 64.00;
            } else if (plan === 'autoline') {
                nom = 'autoline';
                prix = isAnnual ? 719.20 : 89.00;
            }

            const type = isAnnual ? 'annuel' : 'mensuel';

            const jsonData = {
                nom: nom,
                prix: prix,
                type: type,
                timestamp: Date.now(),
                'session-id': crypto.getRandomValues(new Uint8Array(8)).reduce((a, b) => a + b.toString(16).padStart(2, '0'), '')
            };

            const token = btoa(unescape(encodeURIComponent(JSON.stringify(jsonData))));
            const url = `pay-abonnement.php?n=${encodeURIComponent(token)}`;
            window.location.href = url;
        });
    });
});

document.addEventListener("DOMContentLoaded", function () {
  // Récupère tous les boutons dropdown et menus
  const dropdownBtns = document.querySelectorAll('.dropdownBtn');

  dropdownBtns.forEach(btn => {
    const menu = btn.nextElementSibling; // on suppose que le menu est juste après le bouton

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      // Toggle affichage du menu lié
      if (menu.style.display === 'block') {
        menu.style.display = 'none';
      } else {
        // Ferme tous les autres menus avant d’ouvrir celui-ci
        document.querySelectorAll('.dropdownMenu').forEach(m => m.style.display = 'none');
        menu.style.display = 'block';
      }
    });
  });

  // Ferme tous les dropdowns si on clique en dehors
  document.addEventListener('click', function () {
    document.querySelectorAll('.dropdownMenu').forEach(m => m.style.display = 'none');
  });
});
