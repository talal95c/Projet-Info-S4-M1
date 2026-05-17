/*
 * js/admin.js
 * ---------------------------------------------------------------
 * Tableau de bord administrateur — fonctionnalités AJAX.
 *
 * Le sujet phase 3 impose pour le blocage :
 *   « L'administrateur peut bloquer/débloquer un utilisateur en
 *     utilisant des requêtes asynchrones obligatoirement. Si un
 *     utilisateur est bloqué, sa session courante est terminée
 *     sur-le-champ et ne pourra plus continuer à utiliser le site. »
 *
 * Côté client, on ne fait que la requête fetch() et on met à jour
 * le DOM. La déconnexion immédiate de l'utilisateur bloqué est
 * gérée côté serveur (cf. verifier_session_active dans session.php).
 *
 * Comportement :
 *   - Au clic sur un bouton Bloquer/Débloquer, on envoie un POST
 *     JSON vers api/bloquer_utilisateur.php avec { user_id }.
 *   - On met à jour le libellé du bouton, la classe CSS de la
 *     ligne (opacité réduite si bloqué), et on affiche un bandeau
 *     de confirmation temporaire.
 *
 * Dépendances : aucune (vanilla JS).
 */

document.addEventListener('DOMContentLoaded', () => {
    const boutons = document.querySelectorAll('.btn-toggle-actif');
    const banner  = document.getElementById('admin-feedback');

    boutons.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            basculer(btn);
        });
    });

    /**
     * Envoie la requête de blocage/déblocage et met à jour le DOM.
     */
    async function basculer(btn) {
        const userId   = parseInt(btn.dataset.userId, 10);
        const ligne    = btn.closest('.user-row');
        const libelle  = btn.textContent;

        btn.disabled = true;
        btn.textContent = '⏳ ...';

        try {
            const res = await fetch('api/bloquer_utilisateur.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ user_id: userId }),
            });

            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                montrerBanner('erreur', '⚠️ Erreur ' + res.status + ' lors du blocage.');
                btn.textContent = libelle;
                return;
            }

            const data = await res.json();

            if (data.succes) {
                // Met à jour le libellé du bouton et l'aspect de la ligne
                if (data.actif) {
                    btn.textContent = '🚫 Bloquer';
                    btn.classList.remove('btn-debloquer');
                    btn.classList.add('btn-bloquer');
                    if (ligne) ligne.classList.remove('user-bloque');
                } else {
                    btn.textContent = '✅ Débloquer';
                    btn.classList.remove('btn-bloquer');
                    btn.classList.add('btn-debloquer');
                    if (ligne) ligne.classList.add('user-bloque');
                }
                montrerBanner('succes', '✅ ' + data.message);
            } else {
                btn.textContent = libelle;
                montrerBanner('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
            }
        } catch (err) {
            console.error('Erreur avec fetch', err);
            btn.textContent = libelle;
            montrerBanner('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
        } finally {
            btn.disabled = false;
        }
    }

    /**
     * Affiche un bandeau de feedback temporaire (3 secondes).
     */
    function montrerBanner(type, message) {
        if (!banner) return;
        banner.textContent = message;
        banner.className = 'admin-feedback admin-feedback-' + type;
        banner.style.display = 'block';

        clearTimeout(montrerBanner._timer);
        montrerBanner._timer = setTimeout(() => {
            banner.style.display = 'none';
        }, 3500);
    }
});
