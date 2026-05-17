/*
 * js/paiement.js
 * ---------------------------------------------------------------
 * Validation côté client du formulaire de finalisation de commande
 * (étape 1 de paiement.php, avant la redirection vers CY Bank).
 *
 * Comme le paiement par carte se fait DIRECTEMENT sur le site
 * CY Bank, on n'a plus à valider le numéro de carte ici : on
 * valide seulement les informations de livraison et le choix
 * du créneau.
 *
 * Règles :
 *   adresse_livraison : obligatoire, 5 à 200 caractères
 *   code_interphone   : optionnel, max 20 caractères
 *   etage             : optionnel, max 100 caractères
 *   commentaire       : optionnel, max 200 caractères
 *   type_livraison    : 'maintenant' OU 'plus_tard'
 *   date_souhaitee    : obligatoire si type_livraison === 'plus_tard'
 *                       et doit être dans le futur
 *
 * La requête HTTP ne part qu'une fois TOUS les champs validés
 * (e.preventDefault sinon).
 *
 * Dépendances : common.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const formulaire = document.getElementById('form-paiement');
    if (!formulaire) return;   // pas sur la page de saisie

    const adresse        = formulaire.querySelector('input[name="adresse_livraison"]');
    const interphone     = formulaire.querySelector('input[name="code_interphone"]');
    const etage          = formulaire.querySelector('input[name="etage"]');
    const commentaire    = formulaire.querySelector('input[name="commentaire"]');
    const dateSouhaitee  = formulaire.querySelector('input[name="date_souhaitee"]');
    const radiosLivraison = formulaire.querySelectorAll('input[name="type_livraison"]');

    // === Règles de validation ================================== //

    function validerAdresse() {
        const v = adresse.value.trim();
        if (!v)              return ['L\'adresse de livraison est obligatoire.', false];
        if (v.length < 5)    return ['L\'adresse doit faire au moins 5 caractères.', false];
        return ['', true];
    }

    function validerInterphone() {
        const v = interphone.value.trim();
        if (v.length > 20) return ['Code interphone trop long (max 20).', false];
        return ['', true];
    }

    function validerEtage() {
        const v = etage.value.trim();
        if (v.length > 100) return ['Trop long (max 100 caractères).', false];
        return ['', true];
    }

    function validerCommentaire() {
        const v = commentaire.value.trim();
        if (v.length > 200) return ['Commentaire trop long (max 200).', false];
        return ['', true];
    }

    function typeLivraison() {
        let valeur = 'maintenant';
        radiosLivraison.forEach((r) => { if (r.checked) valeur = r.value; });
        return valeur;
    }

    function validerDateSouhaitee() {
        if (typeLivraison() !== 'plus_tard') return ['', true];
        const v = dateSouhaitee.value.trim();
        if (!v) return ['Veuillez choisir une date et heure de livraison.', false];

        const dateChoisie = new Date(v);
        const maintenant  = new Date();
        if (isNaN(dateChoisie.getTime())) return ['Date invalide.', false];
        if (dateChoisie <= maintenant)    return ['La date doit être dans le futur.', false];
        return ['', true];
    }

    // Tableau (champ, fonction) pour boucler à la soumission
    const champsAValider = [
        [adresse,       validerAdresse],
        [interphone,    validerInterphone],
        [etage,         validerEtage],
        [commentaire,   validerCommentaire],
        [dateSouhaitee, validerDateSouhaitee],
    ];

    // === Validation en blur + effacement à la frappe ========== //

    champsAValider.forEach(([champ, regle]) => {
        if (!champ) return;
        champ.addEventListener('blur',  () => appliquerRegle(champ, regle));
        champ.addEventListener('input', () => effacerErreur(champ));
    });

    function appliquerRegle(champ, regle) {
        const [message, ok] = regle();
        if (ok) effacerErreur(champ);
        else     afficherErreur(champ, message);
        return ok;
    }

    // === Validation finale à la soumission ==================== //

    formulaire.addEventListener('submit', (e) => {
        let premierEnErreur = null;
        champsAValider.forEach(([champ, regle]) => {
            if (!champ) return;
            const ok = appliquerRegle(champ, regle);
            if (!ok && !premierEnErreur) premierEnErreur = champ;
        });

        if (premierEnErreur) {
            e.preventDefault();
            premierEnErreur.focus();
        }
    });
});
