/*
 * js/inscription.js
 * ---------------------------------------------------------------
 * Validation côté client du formulaire d'inscription.
 *
 * Règles de validation :
 *   nom               : 2-50 caractères, lettres / espaces / tirets uniquement
 *   prenom            : 2-50 caractères, mêmes règles
 *   login (email)     : format ___@___.__
 *   telephone         : numéro français à 10 chiffres
 *                       (espaces / points / tirets autorisés en saisie)
 *   adresse           : minimum 5 caractères, max 200
 *   code_interphone   : optionnel, max 20 caractères
 *   etage             : optionnel, max 100 caractères
 *   mot_de_passe      : minimum 6 caractères
 *   mot_de_passe2     : doit être identique à mot_de_passe
 *
 * Comportement :
 *   - validation au blur de chaque champ (feedback en direct)
 *   - effacement de l'erreur dès que l'utilisateur retape
 *   - submit bloqué tant qu'un champ est invalide (e.preventDefault)
 *
 * Dépendances : common.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const formulaire = document.querySelector('form.auth-form');
    if (!formulaire) return;

    const champs = {
        nom:              formulaire.querySelector('input[name="nom"]'),
        prenom:           formulaire.querySelector('input[name="prenom"]'),
        login:            formulaire.querySelector('input[name="login"]'),
        telephone:        formulaire.querySelector('input[name="telephone"]'),
        adresse:          formulaire.querySelector('input[name="adresse"]'),
        code_interphone:  formulaire.querySelector('input[name="code_interphone"]'),
        etage:            formulaire.querySelector('input[name="etage"]'),
        mot_de_passe:     formulaire.querySelector('input[name="mot_de_passe"]'),
        mot_de_passe2:    formulaire.querySelector('input[name="mot_de_passe2"]'),
    };

    // Fonctions de validation par champ (retournent true / false)
    const validations = {
        nom: () => {
            const v = champs.nom.value.trim();
            if (!v)                  return ['Le nom est obligatoire.', false];
            if (!estNomValide(v))    return ['Le nom doit contenir 2 à 50 lettres (tirets et espaces autorisés).', false];
            return ['', true];
        },
        prenom: () => {
            const v = champs.prenom.value.trim();
            if (!v)                  return ['Le prénom est obligatoire.', false];
            if (!estNomValide(v))    return ['Le prénom doit contenir 2 à 50 lettres (tirets et espaces autorisés).', false];
            return ['', true];
        },
        login: () => {
            const v = champs.login.value.trim();
            if (!v)                       return ['L\'adresse e-mail est obligatoire.', false];
            if (!estEmailValide(v))       return ['Adresse e-mail invalide (ex : nom@exemple.fr).', false];
            return ['', true];
        },
        telephone: () => {
            const v = champs.telephone.value;
            if (!v.trim())                return ['Le numéro de téléphone est obligatoire.', false];
            if (!estTelephoneValide(v))   return ['Numéro français invalide (10 chiffres, commence par 0).', false];
            return ['', true];
        },
        adresse: () => {
            const v = champs.adresse.value.trim();
            if (!v)              return ['L\'adresse est obligatoire.', false];
            if (v.length < 5)    return ['L\'adresse doit contenir au moins 5 caractères.', false];
            return ['', true];
        },
        code_interphone: () => {
            // Optionnel : pas d'erreur si vide
            const v = champs.code_interphone.value.trim();
            if (v && v.length > 20) return ['Le code interphone est trop long (max 20).', false];
            return ['', true];
        },
        etage: () => {
            const v = champs.etage.value.trim();
            if (v && v.length > 100) return ['Trop long (max 100 caractères).', false];
            return ['', true];
        },
        mot_de_passe: () => {
            const v = champs.mot_de_passe.value;
            if (!v)                          return ['Le mot de passe est obligatoire.', false];
            if (!estMotDePasseValide(v))     return ['Le mot de passe doit faire au moins 6 caractères.', false];
            return ['', true];
        },
        mot_de_passe2: () => {
            const v = champs.mot_de_passe2.value;
            if (!v)                                       return ['Veuillez confirmer le mot de passe.', false];
            if (v !== champs.mot_de_passe.value)          return ['Les mots de passe ne correspondent pas.', false];
            return ['', true];
        }
    };

    // Branchement des écouteurs sur chaque champ
    Object.keys(champs).forEach((cle) => {
        const champ = champs[cle];
        if (!champ) return;

        champ.addEventListener('blur', () => valider(cle));
        champ.addEventListener('input', () => effacerErreur(champ));
    });

    // Si l'utilisateur tape un nouveau mot de passe, on revalide la confirmation
    champs.mot_de_passe.addEventListener('input', () => {
        if (champs.mot_de_passe2.value) effacerErreur(champs.mot_de_passe2);
    });

    // Soumission : valide tout, bloque l'envoi si erreur
    formulaire.addEventListener('submit', (e) => {
        let premierEnErreur = null;
        Object.keys(champs).forEach((cle) => {
            const ok = valider(cle);
            if (!ok && !premierEnErreur) premierEnErreur = champs[cle];
        });

        if (premierEnErreur) {
            e.preventDefault();
            premierEnErreur.focus();
        }
    });

    // Fonction valider(cle) → applique la règle, affiche/efface l'erreur
    function valider(cle) {
        const champ = champs[cle];
        const regle = validations[cle];
        if (!champ || !regle) return true;

        const [message, ok] = regle();
        if (!ok) {
            afficherErreur(champ, message);
        } else {
            effacerErreur(champ);
        }
        return ok;
    }
});
