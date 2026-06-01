/*
 * js/favoris.js
 * ---------------------------------------------------------------
 * Gestion des favoris (menus) côté client.
 *
 * Au clic sur un bouton ❤️ / 🤍 d'un menu :
 *   - Envoi d'un POST asynchrone vers api/favoris.php
 *   - Mise à jour de l'icône du bouton selon la réponse
 *   - Affichage d'un petit message de confirmation temporaire
 *
 * Dépendances : aucune (vanilla JS).
 */

document.addEventListener('DOMContentLoaded', () => {
    // Attacher les écouteurs sur tous les boutons de favoris
    document.querySelectorAll('.btn-favori').forEach((btn) => {
        btn.addEventListener('click', () => toggleFavori(btn));
    });
});

async function toggleFavori(btn) {
    const menuId = parseInt(btn.dataset.menuId, 10);
    if (!menuId) return;

    // Désactiver pendant la requête pour éviter les double-clics
    btn.disabled = true;

    try {
        const res = await fetch('api/favoris.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ menu_id: menuId }),
        });

        if (!res.ok) {
            console.error('Erreur ' + res.status);
            btn.disabled = false;
            return;
        }

        const data = await res.json();

        if (data.succes) {
            // Mettre à jour l'icône et le titre du bouton
            if (data.favori) {
                btn.textContent = '❤️';
                btn.title       = 'Retirer des favoris';
                btn.classList.add('btn-favori-actif');
            } else {
                btn.textContent = '🤍';
                btn.title       = 'Ajouter aux favoris';
                btn.classList.remove('btn-favori-actif');
            }

            // Afficher un petit message temporaire
            afficherToast(data.message);
        }
    } catch (err) {
        console.error('Erreur réseau favoris', err);
    }

    btn.disabled = false;
}

// Affiche un toast (message temporaire) en bas de l'écran
function afficherToast(message) {
    // Supprimer un toast existant si besoin
    const ancien = document.getElementById('favori-toast');
    if (ancien) ancien.remove();

    const toast = document.createElement('div');
    toast.id = 'favori-toast';
    toast.textContent = message;
    toast.style.cssText = [
        'position: fixed',
        'bottom: 30px',
        'left: 50%',
        'transform: translateX(-50%)',
        'background: #014d14',
        'color: white',
        'padding: 0.7rem 1.5rem',
        'border-radius: 50px',
        'font-size: 0.9rem',
        'font-family: Poppins, sans-serif',
        'font-weight: 600',
        'box-shadow: 0 4px 16px rgba(0,0,0,0.25)',
        'z-index: 9999',
        'animation: fadeInUp 0.3s ease',
    ].join('; ');

    document.body.appendChild(toast);

    // Disparaît après 2 secondes
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(() => toast.remove(), 400);
    }, 2000);
}
