/*
 * js/avis.js
 * ---------------------------------------------------------------
 * Notation d'une commande en AJAX (phase 3).
 *
 * Deux responsabilités :
 *   1. Gérer l'interaction des étoiles : clic → coloration jusqu'à
 *      l'étoile cliquée, mise à jour du champ caché note_*.
 *   2. À la soumission du formulaire, valider que les deux notes
 *      sont saisies (1-5), puis envoyer un POST asynchrone vers
 *      api/noter_commande.php. Sur succès, on masque le formulaire
 *      et on affiche un bandeau de remerciement, SANS recharger.
 *
 * Dépendances : common.js (afficherErreur, effacerErreur).
 */

document.addEventListener('DOMContentLoaded', () => {

    // === Gestion des étoiles =================================== //

    document.querySelectorAll('.etoiles').forEach((bloc) => {
        const etoiles = bloc.querySelectorAll('.etoile');
        const input   = document.getElementById('note-' + bloc.id.replace('etoiles-', ''));

        etoiles.forEach((etoile) => {
            etoile.addEventListener('click', () => {
                const val = parseInt(etoile.dataset.value, 10);
                input.value = val;
                etoiles.forEach((e) => {
                    e.classList.toggle('active', parseInt(e.dataset.value, 10) <= val);
                });
                effacerErreur(input);
            });
        });
    });

    // === Soumission AJAX du formulaire ========================= //

    const formulaire = document.getElementById('formulaire-avis');
    const feedback   = document.getElementById('avis-feedback');
    const bouton     = document.getElementById('btn-poster-avis');
    if (!formulaire) return;

    formulaire.addEventListener('submit', async (e) => {
        e.preventDefault();

        const commande_id = parseInt(formulaire.querySelector('input[name="commande_id"]').value, 10);
        const note_livraison = parseInt(formulaire.querySelector('#note-livraison').value, 10);
        const note_produits  = parseInt(formulaire.querySelector('#note-produits').value, 10);
        const commentaire_livraison = formulaire.querySelector('textarea[name="commentaire_livraison"]').value.trim();
        const commentaire_produits  = formulaire.querySelector('textarea[name="commentaire_produits"]').value.trim();

        // Validation client : les deux notes sont obligatoires
        if (!note_livraison || note_livraison < 1 || note_livraison > 5) {
            montrerFeedback('erreur', '⚠️ Choisissez une note de livraison (1 à 5 étoiles).');
            return;
        }
        if (!note_produits || note_produits < 1 || note_produits > 5) {
            montrerFeedback('erreur', '⚠️ Choisissez une note pour les produits (1 à 5 étoiles).');
            return;
        }

        bouton.disabled = true;
        const texteOrigine = bouton.textContent;
        bouton.textContent = '⏳ Envoi…';
        cacherFeedback();

        try {
            const res = await fetch('api/noter_commande.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    commande_id,
                    note_livraison,
                    commentaire_livraison,
                    note_produits,
                    commentaire_produits,
                }),
            });

            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                montrerFeedback('erreur', '⚠️ Erreur ' + res.status + ' lors de l\'envoi de l\'avis.');
                bouton.disabled = false;
                bouton.textContent = texteOrigine;
                return;
            }

            const data = await res.json();

            if (data.succes) {
                // Masque le formulaire et affiche un remerciement
                formulaire.style.display = 'none';
                montrerFeedback('succes', '✅ ' + data.message);
                // Ajoute un lien vers le profil sous le message
                const lien = document.createElement('p');
                lien.style.marginTop = '1rem';
                lien.style.textAlign = 'center';
                lien.innerHTML = '<a href="profil.php">← Retour à mon profil</a>';
                feedback.parentNode.insertBefore(lien, feedback.nextSibling);
            } else {
                montrerFeedback('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
                bouton.disabled = false;
                bouton.textContent = texteOrigine;
            }
        } catch (err) {
            console.error('Erreur avec fetch', err);
            montrerFeedback('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
            bouton.disabled = false;
            bouton.textContent = texteOrigine;
        }
    });

    function montrerFeedback(type, message) {
        if (!feedback) return;
        feedback.className   = 'message ' + (type === 'succes' ? 'succes' : 'erreur');
        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    function cacherFeedback() {
        if (!feedback) return;
        feedback.style.display = 'none';
        feedback.textContent = '';
    }
});
