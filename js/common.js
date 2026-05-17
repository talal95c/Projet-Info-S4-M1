/*
 * js/common.js
 * ---------------------------------------------------------------
 * Bibliothèque JavaScript commune à toutes les pages du site
 * L'Île au Fruit (phase 3).
 *
 * Fonctions disponibles :
 *   initTogglePassword()
 *     → Active les icônes œil 👁 / 🙈 sur tous les champs ayant
 *       l'attribut data-toggle-password. L'œil bascule entre
 *       type="password" et type="text" sans recharger la page.
 *
 *   initCompteursCaracteres()
 *     → Active les compteurs de caractères en temps réel sur
 *       tous les champs ayant l'attribut data-compteur. Affiche
 *       le résultat dans l'élément frère .compteur-info.
 *
 *   afficherErreur(champ, message)
 *     → Affiche un message d'erreur sous un champ, ajoute la
 *       classe .input-erreur. Pas de rechargement.
 *
 *   effacerErreur(champ)
 *     → Retire le message et la classe d'erreur d'un champ.
 *
 *   estEmailValide(s)            → bool — format email simple
 *   estTelephoneValide(s)        → bool — numéro français à 10 chiffres
 *   estMotDePasseValide(s)       → bool — au moins 6 caractères
 *   estTexteNonVide(s, min=1)    → bool — chaîne non vide après trim
 *   estNomValide(s)              → bool — lettres, espaces, tirets
 *
 * Le script s'exécute automatiquement quand le DOM est prêt.
 */

/* ============================================================== */
/*  Utilitaires de validation                                      */
/* ============================================================== */

function estTexteNonVide(s, min) {
    if (min === undefined) min = 1;
    return typeof s === 'string' && s.trim().length >= min;
}

function estEmailValide(s) {
    // format simple : qqchose@qqchose.qqchose
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    return typeof s === 'string' && regex.test(s.trim());
}

function estTelephoneValide(s) {
    // numéro français : 10 chiffres (espaces, points, tirets autorisés)
    if (typeof s !== 'string') return false;
    const chiffres = s.replace(/[\s.\-]/g, '');
    return /^0[1-9]\d{8}$/.test(chiffres);
}

function estMotDePasseValide(s) {
    return typeof s === 'string' && s.length >= 6;
}

function estNomValide(s) {
    // lettres (avec accents), espaces, tirets, apostrophes — 2 à 50 caractères
    if (typeof s !== 'string') return false;
    const regex = /^[A-Za-zÀ-ÿ\s\-']{2,50}$/;
    return regex.test(s.trim());
}

/* ============================================================== */
/*  Affichage / effacement des messages d'erreur                   */
/* ============================================================== */

function afficherErreur(champ, message) {
    if (!champ) return;
    champ.classList.add('input-erreur');

    // Recherche d'un message d'erreur déjà présent juste après le champ
    let zone = champ.parentNode.querySelector('.message-erreur-champ');
    if (!zone) {
        zone = document.createElement('p');
        zone.className = 'message-erreur-champ';
        champ.parentNode.appendChild(zone);
    }
    zone.textContent = '⚠️ ' + message;
}

function effacerErreur(champ) {
    if (!champ) return;
    champ.classList.remove('input-erreur');
    const zone = champ.parentNode.querySelector('.message-erreur-champ');
    if (zone) zone.remove();
}

/* ============================================================== */
/*  Icône œil sur les champs mot de passe                          */
/* ============================================================== */

function initTogglePassword() {
    const champs = document.querySelectorAll('input[data-toggle-password]');
    champs.forEach((champ) => {
        // Encapsuler le champ dans une div relative et ajouter le bouton œil
        if (champ.dataset.toggleInitialise === '1') return;
        champ.dataset.toggleInitialise = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'wrapper-password';
        champ.parentNode.insertBefore(wrapper, champ);
        wrapper.appendChild(champ);

        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'btn-oeil';
        bouton.setAttribute('aria-label', 'Afficher le mot de passe');
        bouton.textContent = '👁';
        wrapper.appendChild(bouton);

        bouton.addEventListener('click', () => {
            if (champ.type === 'password') {
                champ.type = 'text';
                bouton.textContent = '🙈';
                bouton.setAttribute('aria-label', 'Masquer le mot de passe');
            } else {
                champ.type = 'password';
                bouton.textContent = '👁';
                bouton.setAttribute('aria-label', 'Afficher le mot de passe');
            }
        });
    });
}

/* ============================================================== */
/*  Compteurs de caractères en temps réel                          */
/* ============================================================== */

function initCompteursCaracteres() {
    const champs = document.querySelectorAll('[data-compteur]');
    champs.forEach((champ) => {
        if (champ.dataset.compteurInitialise === '1') return;
        champ.dataset.compteurInitialise = '1';

        const max = parseInt(champ.getAttribute('maxlength'), 10) || 0;

        const compteur = document.createElement('span');
        compteur.className = 'compteur-info';
        champ.parentNode.appendChild(compteur);

        function maj() {
            const taille = champ.value.length;
            if (max > 0) {
                compteur.textContent = taille + ' / ' + max + ' caractères';
                if (taille >= max)        compteur.classList.add('compteur-limite');
                else                       compteur.classList.remove('compteur-limite');
            } else {
                compteur.textContent = taille + ' caractères';
            }
        }
        champ.addEventListener('input', maj);
        maj();
    });
}

/* ============================================================== */
/*  Initialisation automatique au chargement de la page            */
/* ============================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initTogglePassword();
    initCompteursCaracteres();
});
