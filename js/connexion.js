/*
 * js/connexion.js
 * ---------------------------------------------------------------
 * Validation côté client du formulaire de connexion.
 *
 * Empêche l'envoi du formulaire vers le serveur tant que :
 *   - l'adresse email n'est pas valide (format ___@___.__)
 *   - le mot de passe est vide
 *
 * Les erreurs s'affichent en direct (sans rechargement) :
 *   - dès la perte de focus (blur) sur un champ
 *   - lors de la soumission du formulaire
 *
 * Dépendances : common.js (afficherErreur, effacerErreur, estEmailValide,
 * estTexteNonVide).
 */

document.addEventListener('DOMContentLoaded', () => {
    const formulaire = document.querySelector('form.auth-form');
    if (!formulaire) return;

    const champLogin = formulaire.querySelector('input[name="login"]');
    const champMdp   = formulaire.querySelector('input[name="mot_de_passe"]');

    // Validation au blur (perte de focus) - feedback immédiat
    champLogin.addEventListener('blur', () => validerLogin(champLogin));
    champMdp.addEventListener('blur', () => validerMotDePasse(champMdp));

    // Efface l'erreur dès que l'utilisateur retape dans le champ
    champLogin.addEventListener('input', () => effacerErreur(champLogin));
    champMdp.addEventListener('input', () => effacerErreur(champMdp));

    // Validation finale à la soumission
    formulaire.addEventListener('submit', (e) => {
        const okLogin = validerLogin(champLogin);
        const okMdp   = validerMotDePasse(champMdp);

        if (!okLogin || !okMdp) {
            e.preventDefault();   // bloque l'envoi au serveur
            // Focus sur le 1er champ en erreur
            if (!okLogin)      champLogin.focus();
            else if (!okMdp)   champMdp.focus();
        }
    });
});

function validerLogin(champ) {
    const valeur = champ.value;
    if (!estTexteNonVide(valeur)) {
        afficherErreur(champ, 'Veuillez saisir votre adresse e-mail.');
        return false;
    }
    if (!estEmailValide(valeur)) {
        afficherErreur(champ, 'Adresse e-mail invalide (ex : nom@exemple.fr).');
        return false;
    }
    effacerErreur(champ);
    return true;
}

function validerMotDePasse(champ) {
    if (!estTexteNonVide(champ.value)) {
        afficherErreur(champ, 'Veuillez saisir votre mot de passe.');
        return false;
    }
    effacerErreur(champ);
    return true;
}
