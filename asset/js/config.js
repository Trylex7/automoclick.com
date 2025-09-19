document.addEventListener('DOMContentLoaded', () => {
    const classesNoTranslate = [
        'material-symbols-outlined'
    ];

    // Fonction pour ajouter translate="no"
    function addNoTranslate() {
        classesNoTranslate.forEach(cls => {
            document.querySelectorAll(`.${cls}`).forEach(el => {
                el.setAttribute('translate', 'no');
            });
        });
    }

    // Exécution initiale
    addNoTranslate();

    // Observer pour les éléments ajoutés dynamiquement
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // element node
                    classesNoTranslate.forEach(cls => {
                        if (node.classList.contains(cls)) {
                            node.setAttribute('translate', 'no');
                        }
                        node.querySelectorAll(`.${cls}`).forEach(el => {
                            el.setAttribute('translate', 'no');
                        });
                    });
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
