
// Fonction pour envoyer des données anonymes au serveur
function sendAnonymousData(data) {
    navigator.sendBeacon("/traker.php", JSON.stringify(data));
}

// Suivi page vue
window.addEventListener("load", () => {
    sendAnonymousData({
        type: "pageview",
        url: window.location.href,
        timestamp: Date.now()
    });
});

// Suivi scroll (anonyme)
window.addEventListener("scroll", () => {
    let scrollPos = window.scrollY || document.documentElement.scrollTop;
    sendAnonymousData({
        type: "scroll",
        position: scrollPos,
        timestamp: Date.now()
    });
});

// Suivi clics (anonyme, sans texte ni identification)
document.addEventListener("click", (e) => {
    sendAnonymousData({
        type: "click",
        tag: e.target.tagName,
        timestamp: Date.now()
    });
});

