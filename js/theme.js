/*
 * js/theme.js
 * ---------------------------------------------------------------
 * Gestion du thème clair / sombre (phase 3, première fonctionnalité
 * obligatoire du sujet).
 *
 * Comportement attendu (cf. PDF phase 3) :
 *   - Un bouton sur l'interface permet de basculer entre les modes.
 *   - L'appui doit charger le nouveau fichier CSS SANS recharger
 *     la page → ici on ajoute / retire dynamiquement une balise
 *     <link rel="stylesheet" href="dark.css"> dans <head>.
 *   - Le choix est sauvegardé dans un COOKIE (pas localStorage).
 *   - À chaque chargement de page, on vérifie le cookie : s'il
 *     n'existe pas ou si sa valeur est incohérente, on utilise
 *     le mode par défaut (clair).
 *
 * Ce script s'exécute le plus tôt possible pour éviter un "flash"
 * du mode clair quand l'utilisateur a sélectionné le mode sombre.
 *
 * Aucune dépendance.
 */

(function () {
    'use strict';

    // === Constantes ============================================ //

    const NOM_COOKIE   = 'theme';
    const ID_LINK_DARK = 'lien-css-dark';
    const FICHIER_DARK = 'dark.css';
    const MODE_CLAIR   = 'clair';
    const MODE_SOMBRE  = 'sombre';
    const DUREE_JOURS  = 365;

    // === Gestion du cookie ===================================== //

    /**
     * Retourne la valeur du cookie demandé, ou null si absent.
     */
    function lireCookie(nom) {
        const recherche = nom + '=';
        const morceaux  = document.cookie.split(';');
        for (let i = 0; i < morceaux.length; i++) {
            const m = morceaux[i].trim();
            if (m.indexOf(recherche) === 0) {
                return decodeURIComponent(m.substring(recherche.length));
            }
        }
        return null;
    }

    /**
     * Écrit un cookie persistant pendant DUREE_JOURS jours.
     */
    function ecrireCookie(nom, valeur) {
        const date = new Date();
        date.setTime(date.getTime() + DUREE_JOURS * 24 * 60 * 60 * 1000);
        document.cookie = nom + '=' + encodeURIComponent(valeur)
                        + '; expires=' + date.toUTCString()
                        + '; path=/'
                        + '; SameSite=Lax';
    }

    /**
     * Retourne le mode courant en se basant sur le cookie.
     * Si le cookie n'existe pas OU contient une valeur invalide,
     * on retourne le mode par défaut (clair), comme demandé.
     */
    function modeActuel() {
        const valeur = lireCookie(NOM_COOKIE);
        if (valeur === MODE_SOMBRE) return MODE_SOMBRE;
        return MODE_CLAIR;
    }

    // === Application du thème ================================== //

    /**
     * Active le mode sombre en injectant le <link> dark.css
     * dans le <head> si pas déjà présent.
     */
    function activerModeSombre() {
        if (document.getElementById(ID_LINK_DARK)) return;
        const lien = document.createElement('link');
        lien.id    = ID_LINK_DARK;
        lien.rel   = 'stylesheet';
        lien.href  = FICHIER_DARK;
        document.head.appendChild(lien);
    }

    /**
     * Désactive le mode sombre en retirant le <link> du <head>.
     */
    function desactiverModeSombre() {
        const lien = document.getElementById(ID_LINK_DARK);
        if (lien) lien.remove();
    }

    /**
     * Applique le mode passé en paramètre (sans modifier le cookie).
     * Met aussi à jour l'icône et le titre du bouton de bascule.
     */
    function appliquerMode(mode) {
        if (mode === MODE_SOMBRE) activerModeSombre();
        else                       desactiverModeSombre();
        majBouton(mode);
    }

    function majBouton(mode) {
        const boutons = document.querySelectorAll('#btn-theme, .btn-theme');
        boutons.forEach((btn) => {
            if (mode === MODE_SOMBRE) {
                btn.textContent = '☀️';
                btn.title       = 'Passer en mode clair';
                btn.setAttribute('aria-label', 'Passer en mode clair');
            } else {
                btn.textContent = '🌙';
                btn.title       = 'Passer en mode sombre';
                btn.setAttribute('aria-label', 'Passer en mode sombre');
            }
        });
    }

    /**
     * Bascule entre les deux modes : sauvegarde le nouveau mode
     * dans le cookie ET applique immédiatement le changement.
     */
    function basculer() {
        const nouveau = modeActuel() === MODE_SOMBRE ? MODE_CLAIR : MODE_SOMBRE;
        ecrireCookie(NOM_COOKIE, nouveau);
        appliquerMode(nouveau);
    }

    // === Initialisation immédiate (avant DOMContentLoaded) ===== //

    // On applique tout de suite le mode sombre si nécessaire pour
    // éviter un flash blanc avant que le DOM ne soit complètement
    // chargé. Le <head> est déjà en cours de lecture donc on peut
    // y injecter le <link>.
    if (modeActuel() === MODE_SOMBRE) {
        activerModeSombre();
    }

    // === Attache des écouteurs après le chargement du DOM ===== //

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Met à jour l'icône du bouton (toujours, même en mode clair)
        majBouton(modeActuel());

        // 2. Si aucun bouton n'est présent (pages connexion/inscription
        //    qui n'utilisent pas nav_html), on en injecte un flottant.
        if (!document.getElementById('btn-theme')) {
            const btn = document.createElement('button');
            btn.id    = 'btn-theme';
            btn.type  = 'button';
            btn.className = 'btn-theme btn-theme-flottant';
            btn.setAttribute('aria-label', 'Basculer le thème');
            document.body.appendChild(btn);
            majBouton(modeActuel());
        }

        // 3. Branche le clic sur TOUS les boutons de thème
        document.querySelectorAll('#btn-theme, .btn-theme').forEach((btn) => {
            btn.addEventListener('click', basculer);
        });
    });
})();
