/*
 * js/livraison.js
 * ---------------------------------------------------------------
 * Marquage AJAX d'une livraison comme effectuée ou abandonnée.
 *
 * Le sujet phase 3 :
 *   « Un livreur peut indiquer qu'une livraison qui lui a été
 *     assignée vient d'être effectuée. »
 *
 * Au clic sur l'un des deux boutons, on demande confirmation
 * puis on envoie un POST asynchrone vers api/livraison_terminee.php
 * avec { commande_id, action: "livree" | "abandonnee" }.
 *
 * Sur succès, on masque la commande de l'écran et on affiche un
 * bandeau de confirmation — l'écran "Aucune livraison en cours"
 * sera visible lors d'une prochaine visite (pas de reload ici).
 *
 * Dépendances : aucune (vanilla JS).
 */

document.addEventListener('DOMContentLoaded', () => {
    const boutons  = document.querySelectorAll('.btn-action-livraison');
    const feedback = document.getElementById('livraison-feedback');

    boutons.forEach((btn) => {
        btn.addEventListener('click', () => terminer(btn));
    });

    async function terminer(btn) {
        const action      = btn.dataset.action;
        const commandeId  = parseInt(btn.dataset.commandeId, 10);

        // Confirmation utilisateur (gros boutons + livreur avec gants,
        // mieux vaut une confirmation pour éviter les fausses manips)
        const messageConfirm = action === 'livree'
            ? 'Confirmer la livraison ?'
            : 'Marquer cette livraison comme abandonnée (adresse introuvable) ?';
        if (!confirm(messageConfirm)) return;

        // Désactive les deux boutons pendant la requête
        document.querySelectorAll('.btn-action-livraison').forEach((b) => b.disabled = true);
        const texteOrigine = btn.textContent;
        btn.textContent = '⏳ ...';

        try {
            const res = await fetch('api/livraison_terminee.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ commande_id: commandeId, action }),
            });

            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                montrerFeedback('erreur', '⚠️ Erreur ' + res.status + ' lors de l\'opération.');
                restaurerBoutons(btn, texteOrigine);
                return;
            }

            const data = await res.json();

            if (data.succes) {
                // Masque la commande en cours et affiche le message de
                // confirmation. Au prochain chargement de la page, l'écran
                // « Aucune livraison en cours » sera affiché par PHP.
                const blocCommande = document.getElementById('bloc-commande');
                const blocBoutons  = document.getElementById('bloc-boutons');
                if (blocCommande) blocCommande.style.display = 'none';
                if (blocBoutons)  blocBoutons.style.display  = 'none';
                document.querySelectorAll('.livraison-infos').forEach((s) => s.style.display = 'none');

                montrerFeedback('succes', data.message);
            } else {
                montrerFeedback('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
                restaurerBoutons(btn, texteOrigine);
            }
        } catch (err) {
            console.error('Erreur avec fetch', err);
            montrerFeedback('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
            restaurerBoutons(btn, texteOrigine);
        }
    }

    function restaurerBoutons(btnClique, texteOrigine) {
        document.querySelectorAll('.btn-action-livraison').forEach((b) => b.disabled = false);
        btnClique.textContent = texteOrigine;
    }

    function montrerFeedback(type, message) {
        if (!feedback) return;
        feedback.className   = 'message ' + (type === 'succes' ? 'succes' : 'erreur');
        feedback.style.background = type === 'succes' ? '#d4edda' : '#f8d7da';
        feedback.style.color      = type === 'succes' ? '#155724' : '#721c24';
        feedback.textContent = message;
        feedback.style.display = 'block';
    }
});
