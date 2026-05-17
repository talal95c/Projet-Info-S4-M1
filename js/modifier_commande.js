/*
 * js/modifier_commande.js
 * ---------------------------------------------------------------
 * Édition d'une commande payée (phase 3 du sujet).
 *
 * Comportement :
 *   - Boutons +/- pour ajuster la quantité de chaque article.
 *   - Bouton 🗑️ pour supprimer un article entièrement.
 *   - Le sous-total et le nouveau total se recalculent en temps
 *     réel (côté client, sans appel serveur).
 *   - Si le nouveau total dépasse le montant déjà payé, on
 *     affiche un message "⚠️ Dépassement" et on désactive le
 *     bouton "Enregistrer" (règle prof : un seul paiement).
 *   - Si le nouveau total est inférieur, on affiche la "perte"
 *     que le client va subir (pas de remboursement).
 *   - Au clic Enregistrer, POST asynchrone vers
 *     api/modifier_commande.php. Retour profil sur succès.
 *
 * Dépendances : aucune (vanilla JS).
 */

document.addEventListener('DOMContentLoaded', () => {
    const bloc = document.getElementById('modif-commande');
    if (!bloc) return;

    const commandeId = parseInt(bloc.dataset.commandeId, 10);
    const totalPaye  = parseFloat(bloc.dataset.totalPaye);

    const elNouveauTotal      = document.getElementById('nouveau-total');
    const elPerteInfo         = document.getElementById('perte-info');
    const elPerteMontant      = document.getElementById('perte-montant');
    const elDepassementInfo   = document.getElementById('depassement-info');
    const elDepassementMont   = document.getElementById('depassement-montant');
    const btnEnregistrer      = document.getElementById('btn-enregistrer-modif');
    const feedback            = document.getElementById('modif-feedback');

    // === Branche les boutons +/- et 🗑️ sur chaque ligne d'article //

    document.querySelectorAll('.ligne-article').forEach((ligne) => {
        const btnMoins   = ligne.querySelector('.btn-moins');
        const btnPlus    = ligne.querySelector('.btn-plus');
        const btnSuppr   = ligne.querySelector('.btn-supprimer');
        const qteAff     = ligne.querySelector('.qte-affichage');

        btnMoins.addEventListener('click', () => {
            let q = parseInt(qteAff.textContent, 10);
            if (q > 1) {
                qteAff.textContent = q - 1;
                majAffichage();
            }
        });

        btnPlus.addEventListener('click', () => {
            let q = parseInt(qteAff.textContent, 10);
            qteAff.textContent = q + 1;
            majAffichage();
            // Si l'augmentation dépasse le budget, on revient en arrière
            // automatiquement après avoir affiché le message.
            if (calculerNouveauTotal() > totalPaye) {
                // On laisse l'utilisateur voir le dépassement, mais le
                // bouton Enregistrer sera désactivé. Pas besoin de
                // reverter automatiquement.
            }
        });

        btnSuppr.addEventListener('click', () => {
            if (confirm('Retirer cet article de la commande ?')) {
                ligne.remove();
                majAffichage();
            }
        });
    });

    // === Calcul et affichage du nouveau total ================== //

    function calculerNouveauTotal() {
        let total = 0;
        document.querySelectorAll('.ligne-article').forEach((ligne) => {
            const prix = parseFloat(ligne.dataset.prix);
            const qte  = parseInt(ligne.querySelector('.qte-affichage').textContent, 10);
            total += prix * qte;
        });
        return Math.round(total * 100) / 100;
    }

    function majAffichage() {
        // Met à jour les sous-totaux de chaque ligne
        document.querySelectorAll('.ligne-article').forEach((ligne) => {
            const prix = parseFloat(ligne.dataset.prix);
            const qte  = parseInt(ligne.querySelector('.qte-affichage').textContent, 10);
            const sousTot = ligne.querySelector('.sous-total');
            sousTot.textContent = formatEuro(prix * qte);
        });

        const nouveau = calculerNouveauTotal();
        elNouveauTotal.textContent = formatEuro(nouveau);

        // Compare au paiement initial
        if (nouveau > totalPaye) {
            // Dépassement — modification impossible
            const depassement = Math.round((nouveau - totalPaye) * 100) / 100;
            elPerteInfo.style.display = 'none';
            elDepassementInfo.style.display = 'block';
            elDepassementMont.textContent = formatEuro(depassement);
            elNouveauTotal.style.color = '#c0392b';
            btnEnregistrer.disabled = true;
        } else {
            // Total OK : on affiche la perte éventuelle
            elDepassementInfo.style.display = 'none';
            elNouveauTotal.style.color = '#014d14';
            btnEnregistrer.disabled = false;

            const perte = Math.round((totalPaye - nouveau) * 100) / 100;
            if (perte > 0) {
                elPerteInfo.style.display = 'block';
                elPerteMontant.textContent = formatEuro(perte);
            } else {
                elPerteInfo.style.display = 'none';
            }
        }

        // Pas d'articles → bouton désactivé
        const nbLignes = document.querySelectorAll('.ligne-article').length;
        if (nbLignes === 0) {
            btnEnregistrer.disabled = true;
        }
    }

    function formatEuro(n) {
        return n.toFixed(2).replace('.', ',') + ' €';
    }

    // Initialisation : calcule l'affichage au chargement
    majAffichage();

    // === Envoi AJAX de la modification ========================= //

    btnEnregistrer.addEventListener('click', async () => {
        // Vérifie qu'il reste au moins un article
        const lignes = document.querySelectorAll('.ligne-article');
        if (lignes.length === 0) {
            montrerFeedback('erreur', '⚠️ La commande ne peut pas être vide.');
            return;
        }

        const articles = [];
        lignes.forEach((ligne) => {
            const type = ligne.dataset.type;
            const id   = parseInt(ligne.dataset.id, 10);
            const qte  = parseInt(ligne.querySelector('.qte-affichage').textContent, 10);
            if (qte <= 0) return;
            if (type === 'menu') articles.push({ menu_id: id, quantite: qte });
            else                 articles.push({ plat_id: id, quantite: qte });
        });

        btnEnregistrer.disabled = true;
        const texteOrigine = btnEnregistrer.textContent;
        btnEnregistrer.textContent = '⏳ Enregistrement…';
        cacherFeedback();

        try {
            const res = await fetch('api/modifier_commande.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ commande_id: commandeId, articles }),
            });

            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                montrerFeedback('erreur', '⚠️ Erreur ' + res.status + '.');
                btnEnregistrer.disabled = false;
                btnEnregistrer.textContent = texteOrigine;
                return;
            }

            const data = await res.json();

            if (data.succes) {
                montrerFeedback('succes', '✅ ' + data.message + ' Redirection vers le profil…');
                setTimeout(() => { window.location.href = 'profil.php'; }, 1500);
            } else {
                montrerFeedback('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
                btnEnregistrer.disabled = false;
                btnEnregistrer.textContent = texteOrigine;
            }
        } catch (err) {
            console.error('Erreur avec fetch', err);
            montrerFeedback('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
            btnEnregistrer.disabled = false;
            btnEnregistrer.textContent = texteOrigine;
        }
    });

    function montrerFeedback(type, message) {
        if (!feedback) return;
        feedback.className   = 'profil-feedback profil-feedback-' + type;
        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    function cacherFeedback() {
        if (!feedback) return;
        feedback.style.display = 'none';
    }
});
