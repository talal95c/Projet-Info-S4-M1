/*
 * js/commandes.js
 * ---------------------------------------------------------------
 * Gestion AJAX du kanban des commandes (restaurateur / admin).
 *
 * Workflow phase 3 du sujet :
 *   en_attente → a_preparer (▶ Lancer la préparation)
 *   a_preparer → prete       (✅ Marquer prête)
 *   prete      → en_livraison (🚴 Assigner et passer en livraison)
 *
 * Comportement :
 *   - Au clic sur un bouton .btn-action-commande, on récupère
 *     l'action et la commande_id depuis data-attributes.
 *   - Pour "assigner_livreur", on lit aussi le select-livreur de
 *     la carte (et on valide que c'est bien sélectionné).
 *   - On envoie un POST asynchrone vers api/changer_statut_commande.php.
 *   - Sur succès, on déplace la carte vers la colonne suivante,
 *     SANS recharger la page. Les compteurs se mettent à jour
 *     automatiquement.
 *
 * Dépendances : aucune (vanilla JS).
 */

document.addEventListener('DOMContentLoaded', () => {

    const feedback = document.getElementById('commandes-feedback');

    // === Liste des livreurs (utile pour rendre le select lors du déplacement) //
    let livreurs = [];
    try {
        const data = document.getElementById('data-livreurs');
        if (data) livreurs = JSON.parse(data.textContent || '[]');
    } catch (e) {
        livreurs = [];
    }

    // === Hook des boutons d'action =============================== //

    document.querySelectorAll('.btn-action-commande').forEach((btn) => {
        btn.addEventListener('click', () => declencherAction(btn));
    });

    async function declencherAction(btn) {
        const action      = btn.dataset.action;
        const commande_id = parseInt(btn.dataset.commandeId, 10);
        const carte       = btn.closest('.commande-card');

        let livreur_id = 0;
        if (action === 'assigner_livreur') {
            const select = carte.querySelector('.select-livreur');
            livreur_id = parseInt(select?.value, 10) || 0;
            if (livreur_id <= 0) {
                montrerFeedback('erreur', '⚠️ Veuillez choisir un livreur avant d\'assigner.');
                return;
            }
        }

        btn.disabled = true;
        const texteOrigine = btn.textContent;
        btn.textContent = '⏳ ...';

        try {
            const res = await fetch('api/changer_statut_commande.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ commande_id, action, livreur_id }),
            });

            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                montrerFeedback('erreur', '⚠️ Erreur ' + res.status + '.');
                btn.disabled = false;
                btn.textContent = texteOrigine;
                return;
            }

            const data = await res.json();

            if (data.succes) {
                montrerFeedback('succes', '✅ ' + data.message);
                deplacerCarte(carte, data.nouveau_statut, livreur_id);
            } else {
                montrerFeedback('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
                btn.disabled = false;
                btn.textContent = texteOrigine;
            }
        } catch (err) {
            console.error('Erreur avec fetch', err);
            montrerFeedback('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
            btn.disabled = false;
            btn.textContent = texteOrigine;
        }
    }

    // === Déplacement de la carte vers la nouvelle colonne ====== //

    /**
     * Retire la carte de sa colonne actuelle et la reconstruit dans
     * la colonne cible. Met à jour les compteurs et les messages
     * "Aucune commande" en conséquence.
     */
    function deplacerCarte(carte, nouveauStatut, livreur_id) {
        const ancienneColonne = carte.parentElement.closest('.commandes-colonne');
        carte.remove();
        majColonne(ancienneColonne);

        // Si la colonne cible n'est pas dans le DOM (ex: en_attente),
        // on s'arrête là.
        const colonneCible = trouverColonneParStatut(nouveauStatut);
        if (!colonneCible) return;

        // Recompose la carte pour la nouvelle colonne en remplaçant
        // simplement les boutons d'action.
        const nouvelleCarte = preparerCartePourStatut(carte, nouveauStatut, livreur_id);
        colonneCible.appendChild(nouvelleCarte);
        majColonne(colonneCible);
    }

    function trouverColonneParStatut(statut) {
        if (statut === 'a_preparer')   return document.querySelector('#colonne-a-preparer');
        if (statut === 'prete')        return document.querySelector('#colonne-prete');
        if (statut === 'en_livraison') return document.querySelector('#colonne-en-livraison');
        return null;
    }

    /**
     * Reconstruit le bloc d'action d'une carte en fonction de son
     * nouveau statut, et retourne la carte modifiée.
     */
    function preparerCartePourStatut(carte, statut, livreur_id) {
        // Retire les anciens contrôles (boutons + select)
        carte.querySelectorAll('.btn-action-commande').forEach((b) => b.remove());
        carte.querySelectorAll('.select-livreur').forEach((s) => s.parentElement.remove());
        // Retire le statut-label s'il existe (mais on le réajoutera plus bas)
        carte.querySelectorAll('.statut-label').forEach((s) => s.remove());

        const commandeId = carte.dataset.commandeId;

        if (statut === 'a_preparer') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-statut btn-action-commande';
            btn.style.background = '#28a745';
            btn.style.marginTop = '0.5rem';
            btn.dataset.action = 'marquer_prete';
            btn.dataset.commandeId = commandeId;
            btn.textContent = '✅ Marquer prête';
            btn.addEventListener('click', () => declencherAction(btn));
            carte.appendChild(btn);

        } else if (statut === 'prete') {
            // Select livreur + bouton assigner
            const wrap = document.createElement('div');
            wrap.style.margin = '0.5rem 0';
            wrap.innerHTML = '<label style="font-size:0.85rem; color:#555;">Livreur :</label>';
            const select = document.createElement('select');
            select.className = 'select-livreur';
            select.style.cssText = 'width:100%; margin-top:4px; padding:6px; border-radius:6px; border:1px solid #ddd;';
            select.innerHTML = '<option value="">-- Choisir un livreur --</option>'
                + livreurs.map((l) => '<option value="' + l.id + '">' + echapper(l.nom) + '</option>').join('');
            wrap.appendChild(select);
            carte.appendChild(wrap);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-statut btn-livraison btn-action-commande';
            btn.dataset.action = 'assigner_livreur';
            btn.dataset.commandeId = commandeId;
            btn.textContent = '🚴 Assigner et passer en livraison';
            btn.addEventListener('click', () => declencherAction(btn));
            carte.appendChild(btn);

        } else if (statut === 'en_livraison') {
            // Affiche le nom du livreur + label "En cours"
            carte.classList.add('en-cours');
            const livreur = livreurs.find((l) => l.id === livreur_id);
            if (livreur) {
                const info = document.createElement('div');
                info.style.cssText = 'font-size:0.85rem; color:#555; margin-top:4px;';
                info.textContent = '🚴 ' + livreur.nom;
                carte.appendChild(info);
            }
            const label = document.createElement('div');
            label.className = 'statut-label';
            label.textContent = '🚴 En cours de livraison...';
            carte.appendChild(label);
        }

        return carte;
    }

    /**
     * Met à jour le compteur de la colonne et l'affichage du
     * message "Aucune commande" en fonction du nombre de cartes.
     */
    function majColonne(colonne) {
        if (!colonne) return;
        const nb = colonne.querySelectorAll('.commande-card').length;

        const badge = colonne.querySelector('.badge-count');
        if (badge) badge.textContent = nb;

        const vide = colonne.querySelector('.vide-colonne');
        if (vide) vide.style.display = nb === 0 ? '' : 'none';
    }

    // === Bandeau de feedback =================================== //

    function montrerFeedback(type, message) {
        if (!feedback) return;
        feedback.className   = 'message ' + (type === 'succes' ? 'succes' : 'erreur');
        feedback.style.background = type === 'succes' ? '#d4edda' : '#f8d7da';
        feedback.style.color      = type === 'succes' ? '#155724' : '#721c24';
        feedback.textContent = message;
        feedback.style.display = 'block';

        clearTimeout(montrerFeedback._timer);
        montrerFeedback._timer = setTimeout(() => {
            feedback.style.display = 'none';
        }, 3500);
    }

    function echapper(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});
