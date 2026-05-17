/*
 * js/profil.js
 * ---------------------------------------------------------------
 * Édition asynchrone du profil client (phase 3, obligatoire).
 *
 * Comportements :
 *   1. Au clic sur ✏️ Modifier, on bascule l'interface en mode édition
 *      SANS recharger la page (l'attribut href reste en fallback no-JS).
 *   2. Au clic sur Annuler, on revient en mode affichage et on
 *      restaure les valeurs initiales dans les champs.
 *   3. À la soumission du formulaire :
 *        - validation côté client (téléphone FR, adresse, longueurs)
 *        - si OK : envoi via fetch() vers api/modifier_profil.php
 *                  en application/json
 *        - succès → on met à jour le DOM (zone d'affichage + bandeau
 *                   vert) et on revient en mode affichage
 *        - erreur → on affiche le message renvoyé par le serveur
 *                   et on focus le champ fautif
 *
 * Dépendances : common.js (validations + UI)
 */

document.addEventListener('DOMContentLoaded', () => {
    const btnModifier      = document.getElementById('btn-modifier-profil');
    const btnAnnuler       = document.getElementById('btn-annuler-profil');
    const formulaire       = document.getElementById('formulaire-profil');
    const blocAffichage    = document.getElementById('bloc-affichage-profil');
    const feedback         = document.getElementById('profil-feedback');

    // Si on est sur la vue admin du profil ou la page d'avis, sortir
    if (!formulaire || !btnModifier) return;

    // Champs du formulaire
    const champs = {
        telephone:       formulaire.querySelector('input[name="telephone"]'),
        adresse:         formulaire.querySelector('input[name="adresse"]'),
        code_interphone: formulaire.querySelector('input[name="code_interphone"]'),
        etage:           formulaire.querySelector('input[name="etage"]'),
    };

    // On mémorise les valeurs initiales pour pouvoir restaurer si Annuler
    const valeursInitiales = {};
    Object.keys(champs).forEach((c) => valeursInitiales[c] = champs[c].value);

    // === Règles de validation côté client (mêmes que côté serveur) ===
    const validations = {
        telephone: () => {
            const v = champs.telephone.value;
            if (!v.trim())              return ['Le téléphone est obligatoire.', false];
            if (!estTelephoneValide(v)) return ['Numéro français invalide (10 chiffres, commence par 0).', false];
            return ['', true];
        },
        adresse: () => {
            const v = champs.adresse.value.trim();
            if (!v)              return ['L\'adresse est obligatoire.', false];
            if (v.length < 5)    return ['L\'adresse doit faire au moins 5 caractères.', false];
            return ['', true];
        },
        code_interphone: () => {
            const v = champs.code_interphone.value.trim();
            if (v.length > 20) return ['Code interphone trop long (max 20).', false];
            return ['', true];
        },
        etage: () => {
            const v = champs.etage.value.trim();
            if (v.length > 100) return ['Trop long (max 100).', false];
            return ['', true];
        },
    };

    // === Bascule affichage <-> édition ===
    function basculerEnEdition(e) {
        if (e) e.preventDefault();   // empêche la navigation vers ?edit=1
        blocAffichage.style.display = 'none';
        formulaire.style.display    = '';
        btnModifier.style.display   = 'none';
        cacherFeedback();
    }

    function basculerEnAffichage(e) {
        if (e) e.preventDefault();
        // Restaure les valeurs initiales (annulation)
        Object.keys(champs).forEach((c) => {
            champs[c].value = valeursInitiales[c];
            effacerErreur(champs[c]);
        });
        formulaire.style.display    = 'none';
        blocAffichage.style.display = '';
        btnModifier.style.display   = '';
    }

    btnModifier.addEventListener('click', basculerEnEdition);
    if (btnAnnuler) btnAnnuler.addEventListener('click', basculerEnAffichage);

    // === Validation au blur et nettoyage à la saisie ===
    Object.keys(champs).forEach((c) => {
        champs[c].addEventListener('blur',  () => valider(c));
        champs[c].addEventListener('input', () => effacerErreur(champs[c]));
    });

    function valider(c) {
        const champ = champs[c];
        const regle = validations[c];
        if (!champ || !regle) return true;
        const [message, ok] = regle();
        if (ok) effacerErreur(champ);
        else     afficherErreur(champ, message);
        return ok;
    }

    // === Soumission AJAX du formulaire ===
    formulaire.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validation finale côté client
        let premierEnErreur = null;
        Object.keys(champs).forEach((c) => {
            const ok = valider(c);
            if (!ok && !premierEnErreur) premierEnErreur = champs[c];
        });
        if (premierEnErreur) {
            premierEnErreur.focus();
            return;
        }

        const corps = {
            telephone:       champs.telephone.value.trim(),
            adresse:         champs.adresse.value.trim(),
            code_interphone: champs.code_interphone.value.trim(),
            etage:           champs.etage.value.trim(),
        };

        const btn = document.getElementById('btn-enregistrer-profil');
        btn.disabled = true;
        const texteOrigine = btn.textContent;
        btn.textContent = '⏳ Enregistrement…';
        cacherFeedback();

        try {
            const reponse = await fetch('api/modifier_profil.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(corps),
            });

            // Cas d'authentification expirée → redirection à la connexion
            if (reponse.status === 401) {
                window.location.href = 'connexion.php';
                return;
            }

            const data = await reponse.json();

            if (data.succes) {
                // Met à jour les valeurs affichées dans le DOM sans reload
                document.querySelector('[data-affichage="telephone"]').textContent       = data.user.telephone;
                document.querySelector('[data-affichage="adresse"]').textContent         = data.user.adresse;
                document.querySelector('[data-affichage="code_interphone"]').textContent = data.user.code_interphone || 'Aucun';
                document.querySelector('[data-affichage="etage"]').textContent           = data.user.etage           || 'Non précisé';

                // Met à jour les valeurs initiales (pour annulations futures)
                valeursInitiales.telephone       = data.user.telephone;
                valeursInitiales.adresse         = data.user.adresse;
                valeursInitiales.code_interphone = data.user.code_interphone || '';
                valeursInitiales.etage           = data.user.etage           || '';

                // Bandeau vert + retour à l'affichage
                montrerFeedback('succes', '✅ ' + data.message);
                formulaire.style.display    = 'none';
                blocAffichage.style.display = '';
                btnModifier.style.display   = '';
            } else {
                // Erreur métier renvoyée par le serveur
                montrerFeedback('erreur', '⚠️ ' + (data.message || 'Erreur inconnue.'));
                if (data.champ && champs[data.champ]) {
                    afficherErreur(champs[data.champ], data.message);
                    champs[data.champ].focus();
                }
            }
        } catch (err) {
            montrerFeedback('erreur', '⚠️ Erreur réseau : impossible de joindre le serveur.');
        } finally {
            btn.disabled = false;
            btn.textContent = texteOrigine;
        }
    });

    function montrerFeedback(type, message) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className   = 'profil-feedback profil-feedback-' + type;
        feedback.style.display = 'block';
    }

    function cacherFeedback() {
        if (!feedback) return;
        feedback.style.display = 'none';
        feedback.textContent   = '';
    }
});
